/**
 * ParkHub API Client
 *
 * CORS Note: The backend must allow the frontend origin (e.g., http://localhost:5173)
 * via Access-Control-Allow-Origin, Access-Control-Allow-Headers (Authorization, Content-Type),
 * and Access-Control-Allow-Methods (GET, POST, PUT, DELETE, OPTIONS).
 */

const API_BASE = import.meta.env.VITE_API_URL || '';

interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: {
    code: string;
    message: string;
  };
}

class ApiClient {
  private token: string | null = null;
  public get baseUrl() { return ''; }
  public get authToken() { return this.getToken(); }
  private refreshingPromise: Promise<boolean> | null = null;

  setToken(token: string | null) {
    this.token = token;
    if (token) {
      localStorage.setItem('parkhub_token', token);
    } else {
      localStorage.removeItem('parkhub_token');
    }
  }

  getToken(): string | null {
    if (!this.token) {
      this.token = localStorage.getItem('parkhub_token');
    }
    return this.token;
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<ApiResponse<T>> {
    const isFormData = options.body instanceof FormData;
    const headers: Record<string, string> = {
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...options.headers as Record<string, string>,
    };

    const token = this.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    try {
      const response = await fetch(`${API_BASE}${endpoint}`, {
        ...options,
        headers,
      });

      // Handle 401 — attempt token refresh once
      if (response.status === 401) {
        const refreshed = await this.tryRefreshToken();
        if (refreshed) {
          const newToken = this.getToken();
          if (newToken) {
            headers['Authorization'] = `Bearer ${newToken}`;
          }
          const retryResponse = await fetch(`${API_BASE}${endpoint}`, {
            ...options,
            headers,
          });
          if (retryResponse.status === 204) return { success: true } as ApiResponse<T>;
          return await retryResponse.json();
        }
        // Don't force redirect - let the component handle auth errors gracefully
        return {
          success: false,
          error: { code: 'UNAUTHORIZED', message: 'Session expired' },
        };
      }

      if (response.status === 204) {
        return { success: true } as ApiResponse<T>;
      }

      const data = await response.json();
      return data;
    } catch (error) {
      return {
        success: false,
        error: {
          code: 'NETWORK_ERROR',
          message: error instanceof Error ? error.message : 'Network error',
        },
      };
    }
  }

  private async tryRefreshToken(): Promise<boolean> {
    if (this.refreshingPromise) return this.refreshingPromise;

    this.refreshingPromise = (async () => {
      // Sanctum uses single access tokens (no separate refresh token).
      // The /auth/refresh endpoint rotates the current token — requires the existing token.
      const currentToken = this.getToken();
      if (!currentToken) return false;
      try {
        const response = await fetch(`${API_BASE}/api/v1/auth/refresh`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${currentToken}`,
          },
        });
        if (!response.ok) return false;
        const result = await response.json();
        // Backend returns { tokens: { access_token, ... } }
        const newToken = result?.tokens?.access_token || result?.data?.tokens?.access_token;
        if (newToken) {
          this.setToken(newToken);
          return true;
        }
        return false;
      } catch {
        return false;
      }
    })();

    const result = await this.refreshingPromise;
    this.refreshingPromise = null;
    return result;
  }



  // Auth
  async login(username: string, password: string): Promise<ApiResponse<{ user: User; tokens: AuthTokens }>> {
    return this.request<{ user: User; tokens: AuthTokens }>('/api/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    });
  }

  async register(data: RegisterData): Promise<ApiResponse<{ user: User; tokens: AuthTokens }>> {
    return this.request<{ user: User; tokens: AuthTokens }>('/api/v1/auth/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async refreshToken(refreshToken: string): Promise<ApiResponse<AuthTokens>> {
    return this.request<AuthTokens>('/api/v1/auth/refresh', {
      method: 'POST',
      body: JSON.stringify({ refresh_token: refreshToken }),
    });
  }

  // Users
  async getCurrentUser(): Promise<ApiResponse<User>> {
    return this.request<User>('/api/v1/users/me');
  }

  async updateMe(data: { name: string; email: string }): Promise<ApiResponse<User>> {
    return this.request<User>('/api/v1/users/me', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  // Lots
  async getLots(): Promise<ApiResponse<ParkingLot[]>> {
    return this.request<ParkingLot[]>('/api/v1/lots');
  }

  async getLot(id: string): Promise<ApiResponse<ParkingLot>> {
    return this.request<ParkingLot>(`/api/v1/lots/${id}`);
  }

  async getLotSlots(lotId: string): Promise<ApiResponse<ParkingSlot[]>> {
    return this.request<ParkingSlot[]>(`/api/v1/lots/${lotId}/slots`);
  }

  async getLotLayout(lotId: string): Promise<ApiResponse<LotLayout>> {
    return this.request<LotLayout>(`/api/v1/lots/${lotId}/layout`);
  }

  async saveLotLayout(lotId: string, layout: LotLayout): Promise<ApiResponse<void>> {
    return this.request<void>(`/api/v1/lots/${lotId}/layout`, {
      method: 'PUT',
      body: JSON.stringify(layout),
    });
  }

  // Bookings
  async getBookings(): Promise<ApiResponse<Booking[]>> {
    return this.request<Booking[]>('/api/v1/bookings');
  }

  async createBooking(data: CreateBookingData): Promise<ApiResponse<Booking>> {
    return this.request<Booking>('/api/v1/bookings', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async cancelBooking(id: string): Promise<ApiResponse<void>> {
    return this.request<void>(`/api/v1/bookings/${id}`, {
      method: 'DELETE',
    });
  }

  // Vehicles
  async getVehicles(): Promise<ApiResponse<Vehicle[]>> {
    return this.request<Vehicle[]>('/api/v1/vehicles');
  }

  async createVehicle(data: CreateVehicleData): Promise<ApiResponse<Vehicle>> {
    return this.request<Vehicle>('/api/v1/vehicles', {
      method: 'POST',
      body: JSON.stringify({ plate: data.plate, make: data.make, model: data.model, color: data.color, photo: data.photo }),
    });
  }

  async deleteVehicle(id: string): Promise<ApiResponse<void>> {
    return this.request<void>(`/api/v1/vehicles/${id}`, {
      method: 'DELETE',
    });
  }

  async uploadVehiclePhoto(vehicleId: string, file: File): Promise<ApiResponse<{ url: string }>> {
    const formData = new FormData();
    formData.append('photo', file);
    return this.request<{ url: string }>(`/api/v1/vehicles/${vehicleId}/photo`, {
      method: 'POST',
      body: formData,
    });
  }

  // Lot detailed — GET /api/v1/lots/:id includes layout
  async getLotDetailed(id: string): Promise<ApiResponse<ParkingLotDetailed>> {
    return this.request<ParkingLotDetailed>(`/api/v1/lots/${id}`);
  }

  // Homeoffice
  async getHomeofficeSettings(): Promise<ApiResponse<HomeofficeSettings>> {
    return this.request<HomeofficeSettings>('/api/v1/homeoffice');
  }

  async updateHomeofficePattern(weekdays: number[]): Promise<ApiResponse<void>> {
    return this.request<void>('/api/v1/homeoffice/pattern', { method: 'PUT', body: JSON.stringify({ weekdays }) });
  }

  async addHomeofficeDay(date: string, reason?: string): Promise<ApiResponse<HomeofficeDay>> {
    return this.request<HomeofficeDay>('/api/v1/homeoffice/days', { method: 'POST', body: JSON.stringify({ date, reason }) });
  }

  async removeHomeofficeDay(id: string): Promise<ApiResponse<void>> {
    return this.request<void>(`/api/v1/homeoffice/days/${id}`, { method: 'DELETE' });
  }

  // Vacation
  async listVacation(): Promise<ApiResponse<VacationEntry[]>> {
    return this.request<VacationEntry[]>("/api/v1/vacation");
  }

  async createVacation(start_date: string, end_date: string, note?: string): Promise<ApiResponse<VacationEntry>> {
    return this.request<VacationEntry>("/api/v1/vacation", { method: "POST", body: JSON.stringify({ start_date, end_date, note }) });
  }

  async deleteVacation(id: string): Promise<ApiResponse<void>> {
    return this.request<void>(`/api/v1/vacation/${id}`, { method: "DELETE" });
  }

  async teamVacation(): Promise<ApiResponse<TeamVacationEntry[]>> {
    return this.request<TeamVacationEntry[]>("/api/v1/vacation/team");
  }

  async importVacationIcal(file: File): Promise<ApiResponse<VacationEntry[]>> {
    const formData = new FormData();
    formData.append("file", file);
    const token = this.getToken();
    const resp = await fetch(`${API_BASE}/api/v1/vacation/import`, { method: "POST", headers: token ? { Authorization: `Bearer ${token}` } : {}, body: formData });
    return resp.json();
  }


  // ── Absences (unified) ──

  async listAbsences(type?: string): Promise<ApiResponse<AbsenceEntry[]>> {
    const q = type ? `?type=${type}` : '';
    return this.request<AbsenceEntry[]>(`/api/v1/absences${q}`);
  }

  async createAbsence(absence_type: string, start_date: string, end_date: string, note?: string): Promise<ApiResponse<AbsenceEntry>> {
    return this.request<AbsenceEntry>('/api/v1/absences', { method: 'POST', body: JSON.stringify({ absence_type, start_date, end_date, note }) });
  }

  async deleteAbsence(id: string): Promise<ApiResponse<void>> {
    return this.request<void>(`/api/v1/absences/${id}`, { method: 'DELETE' });
  }

  async importAbsenceIcal(file: File): Promise<ApiResponse<AbsenceEntry[]>> {
    const formData = new FormData();
    formData.append('file', file);
    const token = this.getToken();
    const resp = await fetch(`${API_BASE}/api/v1/absences/import`, { method: 'POST', headers: token ? { Authorization: `Bearer ${token}` } : {}, body: formData });
    return resp.json();
  }

  async teamAbsences(): Promise<ApiResponse<TeamAbsenceEntry[]>> {
    return this.request<TeamAbsenceEntry[]>('/api/v1/absences/team');
  }

  async getAbsencePattern(): Promise<ApiResponse<AbsencePattern[]>> {
    return this.request<AbsencePattern[]>('/api/v1/absences/pattern');
  }

  async setAbsencePattern(absence_type: string, weekdays: number[]): Promise<ApiResponse<AbsencePattern>> {
    return this.request<AbsencePattern>('/api/v1/absences/pattern', { method: 'POST', body: JSON.stringify({ absence_type, weekdays }) });
  }

  // Admin
  async getAdminUsers(): Promise<ApiResponse<User[]>> {
    return this.request<User[]>('/api/v1/admin/users');
  }

  async getAdminBookings(): Promise<ApiResponse<Booking[]>> {
    return this.request<Booking[]>('/api/v1/admin/bookings');
  }

  async getAdminStats(): Promise<ApiResponse<AdminStats>> {
    return this.request<AdminStats>('/api/v1/admin/stats');
  }


  // Dashboard Charts
  async getDashboardCharts(): Promise<ApiResponse<DashboardChartData>> {
    return this.request<DashboardChartData>('/api/v1/admin/dashboard/charts');
  }

  // Calendar Events
  async getCalendarEvents(from?: string, to?: string): Promise<ApiResponse<CalendarEvent[]>> {
    const params = new URLSearchParams();
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    const q = params.toString() ? `?${params.toString()}` : '';
    return this.request<CalendarEvent[]>(`/api/v1/calendar/events${q}`);
  }

  // Team Today
  async getTeamToday(): Promise<ApiResponse<TeamMember[]>> {
    return this.request<TeamMember[]>('/api/v1/team/today');
  }

  // Recurring Bookings
  async createRecurringBooking(data: RecurringBookingData): Promise<ApiResponse<Booking[]>> {
    return this.request<Booking[]>('/api/v1/recurring-bookings', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // Guest Bookings
  async createGuestBooking(data: GuestBookingData): Promise<ApiResponse<GuestBookingResponse>> {
    return this.request<GuestBookingResponse>('/api/v1/bookings/guest', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // Favorites
  async addFavoriteSlot(slotId: string): Promise<ApiResponse<void>> {
    return this.request<void>('/api/v1/user/favorites', { method: 'POST', body: JSON.stringify({ slot_id: slotId }) });
  }

  async removeFavoriteSlot(slotId: string): Promise<ApiResponse<void>> {
    return this.request<void>(`/api/v1/user/favorites/${slotId}`, { method: 'DELETE' });
  }

  // Auto-Release Settings
  async getAutoReleaseSettings(): Promise<ApiResponse<AutoReleaseSettings>> {
    return this.request<AutoReleaseSettings>('/api/v1/admin/settings/auto-release');
  }

  async updateAutoReleaseSettings(data: AutoReleaseSettings): Promise<ApiResponse<AutoReleaseSettings>> {
    return this.request<AutoReleaseSettings>('/api/v1/admin/settings/auto-release', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  // Email Settings
  async getEmailSettings(): Promise<ApiResponse<EmailSettings>> {
    return this.request<EmailSettings>('/api/v1/admin/settings/email');
  }

  async updateEmailSettings(data: EmailSettings): Promise<ApiResponse<EmailSettings>> {
    return this.request<EmailSettings>('/api/v1/admin/settings/email', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  // Push Unsubscribe
  async pushUnsubscribe(): Promise<ApiResponse<void>> {
    return this.request<void>('/api/v1/push/unsubscribe', { method: 'DELETE' });
  }
  // Health
  async health() {
    return this.request<{ status: string }>('/health');
  }

  // ═══ Round 2 API Methods ═══

  async quickBook(): Promise<ApiResponse<QuickBookResult>> {
    return this.request<QuickBookResult>('/api/v1/bookings/quick', { method: 'POST', body: JSON.stringify({}) });
  }

  async getUserStats(): Promise<ApiResponse<UserStats>> {
    return this.request<UserStats>('/api/v1/user/stats');
  }

  async getAdminHeatmap(): Promise<ApiResponse<HeatmapEntry[]>> {
    return this.request<HeatmapEntry[]>('/api/v1/admin/heatmap');
  }

  async getNotifications(): Promise<ApiResponse<ApiNotification[]>> {
    return this.request<ApiNotification[]>('/api/v1/notifications');
  }

  async markNotificationRead(id: string): Promise<ApiResponse<void>> {
    return this.request<void>(`/api/v1/notifications/${id}/read`, { method: 'PUT' });
  }

  async markAllNotificationsRead(): Promise<ApiResponse<void>> {
    return this.request<void>('/api/v1/notifications/read-all', { method: 'POST' });
  }

  async getActiveAnnouncements(): Promise<ApiResponse<Announcement[]>> {
    return this.request<Announcement[]>('/api/v1/announcements/active');
  }

  async createAnnouncement(data: { title: string; message: string; severity: string; expires_at?: string }): Promise<ApiResponse<Announcement>> {
    return this.request<Announcement>('/api/v1/admin/announcements', { method: 'POST', body: JSON.stringify(data) });
  }

  async deleteAnnouncement(id: string): Promise<ApiResponse<void>> {
    return this.request<void>(`/api/v1/admin/announcements/${id}`, { method: 'DELETE' });
  }

  async getAuditLog(page?: number, limit?: number): Promise<ApiResponse<AuditLogEntry[]>> {
    const params = new URLSearchParams();
    if (page) params.set('page', String(page));
    if (limit) params.set('limit', String(limit));
    const q = params.toString() ? `?${params.toString()}` : '';
    return this.request<AuditLogEntry[]>(`/api/v1/admin/audit-log${q}`);
  }

  async getUserPreferences(): Promise<ApiResponse<UserPreferences>> {
    return this.request<UserPreferences>('/api/v1/user/preferences');
  }

  async updateUserPreferences(prefs: UserPreferences): Promise<ApiResponse<UserPreferences>> {
    return this.request<UserPreferences>('/api/v1/user/preferences', { method: 'PUT', body: JSON.stringify(prefs) });
  }

  async getLotQrCode(lotId: string): Promise<string> {
    const token = this.getToken();
    const resp = await fetch(`${API_BASE}/api/v1/lots/${lotId}/qr`, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
    return resp.text();
  }
}


// ═══ Round 2 Types ═══

export interface QuickBookResult {
  booking?: Booking;
  error?: string;
  alternatives?: { slot_id: string; slot_number: string; lot_name: string }[];
}

export interface UserStats {
  total_bookings: number;
  bookings_this_month: number;
  homeoffice_days_this_month: number;
  avg_duration_minutes: number;
  favorite_slot?: string;
  favorite_lot?: string;
}

export interface HeatmapEntry {
  day: number;
  hour: number;
  count: number;
}

export interface ApiNotification {
  id: string;
  user_id: string;
  title: string;
  message: string;
  notification_type: string;
  read: boolean;
  created_at: string;
}

export interface Announcement {
  id: string;
  title: string;
  message: string;
  severity: 'info' | 'warning' | 'error' | 'success';
  active: boolean;
  created_at: string;
  expires_at?: string;
}

export interface AuditLogEntry {
  id: string;
  user_id?: string;
  user_name?: string;
  action: string;
  details?: string;
  ip_address?: string;
  created_at: string;
}

export interface UserPreferences {
  theme: 'light' | 'dark' | 'system';
  language: string;
  notifications_enabled: boolean;
}

export const api = new ApiClient();

// Types
export interface User {
  id: string;
  username: string;
  email: string;
  name: string;
  role: 'user' | 'admin' | 'superadmin';
  created_at: string;
}

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
}

export interface RegisterData {
  username: string;
  email: string;
  password: string;
  name: string;
}

export interface ParkingLot {
  id: string;
  name: string;
  address: string;
  total_slots: number;
  available_slots: number;
  layout?: LotLayout;
}

export interface ParkingSlot {
  id: string;
  lot_id: string;
  number: string;
  status: 'available' | 'occupied' | 'reserved' | 'disabled';
  floor?: number;
  section?: string;
}

export interface Booking {
  id: string;
  user_id: string;
  slot_id: string;
  lot_id: string;
  slot_number: string;
  lot_name: string;
  vehicle_plate?: string;
  start_time: string;
  end_time: string;
  status: 'active' | 'completed' | 'cancelled' | 'confirmed';
  booking_type?: 'einmalig' | 'mehrtaegig' | 'dauer';
  dauer_interval?: 'weekly' | 'monthly';
  created_at: string;
}

export interface CreateBookingData {  lot_id: string;  slot_id: string;  start_time: string;  duration_minutes: number;  vehicle_id?: string;  license_plate?: string;  notes?: string;}

export interface Vehicle {
  id: string;
  user_id: string;
  plate: string;
  make?: string;
  model?: string;
  color?: string;
  photo_url?: string;
  is_default: boolean;
}

// Generate SVG car placeholder with given color
export function generateCarPhotoSvg(color: string): string {
  const colorHex: Record<string, string> = {
    'Schwarz': '#1f2937', 'Weiß': '#e5e7eb', 'Silber': '#9ca3af', 'Grau': '#6b7280',
    'Blau': '#2563eb', 'Rot': '#dc2626', 'Grün': '#16a34a', 'Gelb': '#eab308',
  };
  const bg = colorHex[color] || '#6b7280';
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
    <rect width="200" height="200" rx="20" fill="${bg}"/>
    <g transform="translate(40,50)" fill="rgba(255,255,255,0.3)">
      <path d="M10,70 L20,35 Q25,20 40,15 L80,10 Q95,8 105,15 L115,25 Q120,30 120,40 L120,70 Q120,80 110,80 L20,80 Q10,80 10,70 Z"/>
      <rect x="20" y="75" width="25" height="12" rx="6" fill="rgba(255,255,255,0.25)"/>
      <rect x="85" y="75" width="25" height="12" rx="6" fill="rgba(255,255,255,0.25)"/>
      <rect x="35" y="30" width="22" height="18" rx="4" fill="rgba(255,255,255,0.15)"/>
      <rect x="65" y="28" width="22" height="18" rx="4" fill="rgba(255,255,255,0.15)"/>
    </g>
  </svg>`;
  return `data:image/svg+xml,${encodeURIComponent(svg)}`;
}

export interface CreateVehicleData {
  plate: string;
  make?: string;
  model?: string;
  color?: string;
  is_default?: boolean;
  photo?: string;
}

// Parking lot layout configuration
export interface LotLayout {
  rows: LotRow[];
  roadLabel?: string;
}

export interface LotRow {
  id: string;
  side: 'top' | 'bottom';
  slots: SlotConfig[];
  label?: string;
}

export interface SlotConfig {
  id: string;
  number: string;
  status: 'available' | 'occupied' | 'reserved' | 'disabled' | 'blocked' | 'homeoffice';
  vehiclePlate?: string;
  bookedBy?: string;
  homeofficeUser?: string;
}

export interface ParkingLotDetailed extends ParkingLot {
  layout?: LotLayout;
}

// Homeoffice types
export interface HomeofficePattern {
  weekdays: number[]; // 0=Mon, 1=Tue, ... 4=Fri
}

export interface HomeofficeDay {
  id: string;
  date: string; // ISO date
  reason?: string;
}

export interface HomeofficeSettings {
  pattern: HomeofficePattern;
  single_days: HomeofficeDay[];
  parkingSlot?: { number: string; lotName: string };
}

export interface VacationEntry {  id: string;  user_id: string;  start_date: string;  end_date: string;  note?: string;  source: "Manual" | "Import";}export interface TeamVacationEntry {  user_name: string;  start_date: string;  end_date: string;}

// Absence types (unified)
export interface AbsenceEntry {
  id: string;
  user_id: string;
  absence_type: string;
  start_date: string;
  end_date: string;
  note?: string;
  source: string;
  created_at: string;
}

export interface TeamAbsenceEntry {
  user_name: string;
  absence_type: string;
  start_date: string;
  end_date: string;
}

export interface AbsencePattern {
  user_id: string;
  absence_type: string;
  weekdays: number[];
}
// Admin types
export interface AdminStats {
  total_users: number;
  total_lots: number;
  total_bookings: number;
  active_bookings: number;
  bookings_today: number;
}

// ═══════════════════════════════════════════════════════════════════════════════
// BRANDING
// ═══════════════════════════════════════════════════════════════════════════════

export interface BrandingConfig {
  company_name: string;
  primary_color: string;
  secondary_color: string;
  logo_url: string | null;
  favicon_url: string | null;
  login_background_color: string;
  custom_css: string | null;
}

export async function getBranding(): Promise<ApiResponse<BrandingConfig>> {
  try {
    const response = await fetch(`${API_BASE}/api/v1/branding`, {
      headers: { 'Content-Type': 'application/json' },
    });
    return await response.json();
  } catch {
    return { success: false, error: { code: 'NETWORK_ERROR', message: 'Failed to fetch branding' } };
  }
}

export async function updateBranding(config: BrandingConfig): Promise<ApiResponse<BrandingConfig>> {
  const token = api.getToken();
  try {
    const response = await fetch(`${API_BASE}/api/v1/admin/branding`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      },
      body: JSON.stringify(config),
    });
    return await response.json();
  } catch {
    return { success: false, error: { code: 'NETWORK_ERROR', message: 'Failed to update branding' } };
  }
}

export async function uploadBrandingLogo(file: File): Promise<ApiResponse<{ logo_url: string }>> {
  const token = api.getToken();
  const formData = new FormData();
  formData.append('logo', file);
  try {
    const response = await fetch(`${API_BASE}/api/v1/admin/branding/logo`, {
      method: 'POST',
      headers: {
        ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      },
      body: formData,
    });
    return await response.json();
  } catch {
    return { success: false, error: { code: 'NETWORK_ERROR', message: 'Failed to upload logo' } };
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// NEW FEATURE API METHODS
// ═══════════════════════════════════════════════════════════════════════════════

// Types for new features
export interface DashboardChartData {
  booking_trend_7d: { date: string; count: number }[];
  booking_trend_30d: { date: string; count: number }[];
  occupancy_trend: { date: string; rate: number }[];
  peak_hours: { hour: number; count: number }[];
}

export interface CalendarEvent {
  id: string;
  title: string;
  start: string;
  end: string;
  status: string;
  slot_number?: string;
  lot_name?: string;
  is_recurring: boolean;
  is_guest: boolean;
}

export interface TeamMember {
  user_id: string;
  name: string;
  status: 'parked' | 'homeoffice' | 'vacation' | 'sick' | 'business_trip' | 'not_scheduled';
  slot_number?: string;
  lot_name?: string;
  department?: string;
}

export interface AutoReleaseSettings {
  timeout_minutes: number;
}

export interface EmailSettings {
  smtp_host: string;
  smtp_port: number;
  smtp_user: string;
  smtp_pass: string;
  from_address: string;
  enabled: boolean;
}

export interface GuestBookingResponse {
  booking: Booking;
  guest_code: string;
  share_link: string;
}

export interface RecurringBookingData {
  lot_id: string;
  slot_id: string;
  days_of_week: number[];
  start_time: string;
  end_time: string;
  start_date: string;
  end_date: string;
  license_plate?: string;
  notes?: string;
}

export interface GuestBookingData {
  lot_id: string;
  slot_id: string;
  guest_name: string;
  start_time: string;
  end_time: string;
  license_plate?: string;
}
