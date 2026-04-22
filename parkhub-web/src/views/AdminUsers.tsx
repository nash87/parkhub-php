import { useState, useEffect, useRef, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { createColumnHelper } from '@tanstack/react-table';
import {
  SpinnerGap, MagnifyingGlass, Coins,
  PencilSimple, X, Check, UserMinus, UserPlus,
  Lightning,
} from '@phosphor-icons/react';
import { api, type User } from '../api/client';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import { DataTable } from '../components/ui/DataTable';
import { useTheme } from '../context/ThemeContext';

const columnHelper = createColumnHelper<User>();

export function AdminUsersPage() {
  const { t } = useTranslation();
  const { designTheme } = useTheme();
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editRole, setEditRole] = useState('');
  const [creditUserId, setCreditUserId] = useState<string | null>(null);
  const [creditAmount, setCreditAmount] = useState('');
  const [creditDesc, setCreditDesc] = useState('');
  const [savingRole, setSavingRole] = useState(false);
  const [grantingCredits, setGrantingCredits] = useState(false);
  const [editingQuotaId, setEditingQuotaId] = useState<string | null>(null);
  const [editQuota, setEditQuota] = useState('');
  const [savingQuota, setSavingQuota] = useState(false);
  // Bulk actions
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkAction, setBulkAction] = useState('');
  const [bulkRole, setBulkRole] = useState('user');
  const [bulkRunning, setBulkRunning] = useState(false);
  const [bulkConfirm, setBulkConfirm] = useState(false);
  const isVoid = designTheme === 'void';
  const surfaceVariant = isVoid ? 'void' : 'marble';
  const activeUsers = users.filter(user => user.is_active).length;
  const adminUsers = users.filter(user => ['admin', 'superadmin'].includes(user.role)).length;
  const totalCredits = users.reduce((sum, user) => sum + user.credits_balance, 0);
  const totalQuota = users.reduce((sum, user) => sum + user.credits_monthly_quota, 0);

  useEffect(() => { loadUsers(); }, []);

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => setDebouncedSearch(search), 200);
    return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
  }, [search]);

  async function loadUsers() {
    try {
      const res = await api.adminUsers();
      if (res.success && res.data) setUsers(res.data);
    } finally {
      setLoading(false);
    }
  }

  function startEditRole(user: User) {
    setEditingId(user.id);
    setEditRole(user.role);
  }

  async function saveRole(userId: string) {
    setSavingRole(true);
    try {
      const res = await api.adminUpdateUserRole(userId, editRole);
      if (res.success) {
        setUsers(prev => prev.map(u => u.id === userId ? { ...u, role: editRole as User['role'] } : u));
        toast.success(t('admin.roleUpdated'));
        setEditingId(null);
      } else {
        toast.error(res.error?.message || t('admin.roleUpdateFailed'));
      }
    } finally {
      setSavingRole(false);
    }
  }

  async function toggleActive(user: User) {
    const res = await api.adminUpdateUser(user.id, { is_active: !user.is_active });
    if (res.success) {
      setUsers(prev => prev.map(u => u.id === user.id ? { ...u, is_active: !u.is_active } : u));
      toast.success(user.is_active ? t('admin.userDeactivated') : t('admin.userActivated'));
    } else {
      toast.error(res.error?.message || t('admin.userUpdateFailed'));
    }
  }

  async function handleGrantCredits() {
    if (!creditUserId || !creditAmount) return;
    setGrantingCredits(true);
    try {
      const res = await api.adminGrantCredits(creditUserId, Number(creditAmount), creditDesc || undefined);
      if (res.success) {
        toast.success(t('admin.creditsGranted'));
        setCreditUserId(null);
        setCreditAmount('');
        setCreditDesc('');
        await loadUsers();
      } else {
        toast.error(res.error?.message || t('admin.creditsGrantFailed'));
      }
    } finally {
      setGrantingCredits(false);
    }
  }

  function startEditQuota(user: User) {
    setEditingQuotaId(user.id);
    setEditQuota(String(user.credits_monthly_quota));
  }

  async function saveQuota(userId: string) {
    const quota = Number(editQuota);
    if (isNaN(quota) || quota < 0 || quota > 999) {
      toast.error(t('admin.quotaRange'));
      return;
    }
    setSavingQuota(true);
    try {
      const res = await api.adminUpdateUserQuota(userId, quota);
      if (res.success) {
        setUsers(prev => prev.map(u => u.id === userId ? { ...u, credits_monthly_quota: quota } : u));
        toast.success(t('admin.quotaUpdated'));
        setEditingQuotaId(null);
      } else {
        toast.error(res.error?.message || t('admin.quotaUpdateFailed'));
      }
    } finally {
      setSavingQuota(false);
    }
  }

  async function handleBulkAction() {
    if (selectedIds.size === 0 || !bulkAction) return;
    setBulkRunning(true);
    try {
      if (bulkAction === 'delete') {
        const res = await api.adminBulkDelete(Array.from(selectedIds));
        if (res.success && res.data) {
          toast.success(t('admin.bulkDeleted', { succeeded: res.data.succeeded, total: res.data.total }));
          await loadUsers();
          setSelectedIds(new Set());
        } else {
          toast.error(res.error?.message || t('admin.bulkDeleteFailed'));
        }
      } else {
        const res = await api.adminBulkUpdate(
          Array.from(selectedIds),
          bulkAction,
          bulkAction === 'set_role' ? bulkRole : undefined,
        );
        if (res.success && res.data) {
          toast.success(t('admin.bulkUpdated', { succeeded: res.data.succeeded, total: res.data.total }));
          await loadUsers();
          setSelectedIds(new Set());
        } else {
          toast.error(res.error?.message || t('admin.bulkUpdateFailed'));
        }
      }
    } finally {
      setBulkRunning(false);
      setBulkConfirm(false);
      setBulkAction('');
    }
  }

  function roleBadge(role: string) {
    const colors: Record<string, string> = {
      superadmin: 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400',
      admin: 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400',
      user: 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400',
    };
    return (
      <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${colors[role] || colors.user}`}>
        {role}
      </span>
    );
  }

  const columns = useMemo(() => [
    columnHelper.accessor('name', {
      header: () => t('admin.users'),
      cell: info => (
        <div className="min-w-0">
          <p className="text-sm font-medium text-surface-900 dark:text-white truncate">{info.getValue()}</p>
          <p className="text-xs text-surface-500 dark:text-surface-400 truncate">{info.row.original.email}</p>
        </div>
      ),
      enableSorting: true,
    }),
    columnHelper.accessor('role', {
      header: () => t('admin.editRole'),
      cell: info => {
        const user = info.row.original;
        if (editingId === user.id) {
          return (
            <div className="flex items-center gap-2">
              <select value={editRole} onChange={e => setEditRole(e.target.value)} className="input text-xs py-1 px-2 w-28">
                <option value="user">user</option>
                <option value="admin">admin</option>
                <option value="superadmin">superadmin</option>
              </select>
              <button onClick={() => saveRole(user.id)} disabled={savingRole} className="p-1 rounded hover:bg-emerald-100 dark:hover:bg-emerald-900/30 text-emerald-600" aria-label={t('common.save')}>
                {savingRole ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" /> : <Check weight="bold" className="w-4 h-4" />}
              </button>
              <button onClick={() => setEditingId(null)} className="p-1 rounded hover:bg-surface-100 dark:hover:bg-surface-800 text-surface-400" aria-label={t('common.cancel')}>
                <X weight="bold" className="w-4 h-4" />
              </button>
            </div>
          );
        }
        return roleBadge(user.role);
      },
      enableSorting: true,
    }),
    columnHelper.accessor('credits_balance', {
      header: () => t('admin.credits'),
      cell: info => <span className="text-sm font-semibold text-surface-900 dark:text-white tabular-nums">{info.getValue()}</span>,
      enableSorting: true,
    }),
    columnHelper.accessor('credits_monthly_quota', {
      header: () => t('admin.monthlyQuota'),
      cell: info => {
        const user = info.row.original;
        if (editingQuotaId === user.id) {
          return (
            <div className="flex items-center gap-2">
              <input type="number" min={0} max={999} value={editQuota} onChange={e => setEditQuota(e.target.value)} className="input text-xs py-1 px-2 w-20" aria-label={t('admin.monthlyQuota')} />
              <button onClick={() => saveQuota(user.id)} disabled={savingQuota} className="p-1 rounded hover:bg-emerald-100 dark:hover:bg-emerald-900/30 text-emerald-600" aria-label={t('admin.saveQuota')}>
                {savingQuota ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" /> : <Check weight="bold" className="w-4 h-4" />}
              </button>
              <button onClick={() => setEditingQuotaId(null)} className="p-1 rounded hover:bg-surface-100 dark:hover:bg-surface-800 text-surface-400" aria-label={t('admin.cancelEditQuota')}>
                <X weight="bold" className="w-4 h-4" />
              </button>
            </div>
          );
        }
        return (
          <button
            onClick={() => startEditQuota(user)}
            className="inline-flex items-center gap-1.5 text-sm text-surface-700 dark:text-surface-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            aria-label={t('admin.editQuota', { name: user.name })}
          >
            <span className="font-semibold tabular-nums">{user.credits_monthly_quota}</span>
          </button>
        );
      },
      enableSorting: true,
    }),
    columnHelper.accessor('is_active', {
      header: () => t('admin.status'),
      cell: info => (
        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
          info.getValue()
            ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400'
            : 'bg-surface-100 dark:bg-surface-800 text-surface-500 dark:text-surface-400'
        }`}>
          {info.getValue() ? t('admin.active') : t('admin.inactive')}
        </span>
      ),
      enableSorting: true,
    }),
    columnHelper.display({
      id: 'actions',
      header: '',
      cell: info => {
        const user = info.row.original;
        return (
          <div className="flex items-center justify-end gap-1">
            <button
              onClick={() => startEditRole(user)}
              className="p-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors text-surface-400 hover:text-primary-600"
              title={t('admin.editRole')}
              aria-label={`${t('admin.editRole')} ${user.name}`}
            >
              <PencilSimple weight="bold" className="w-4 h-4" />
            </button>
            <button
              onClick={() => { setCreditUserId(user.id); setCreditAmount(''); setCreditDesc(''); }}
              className="p-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors text-surface-400 hover:text-accent-600"
              title={t('admin.grantCreditsFor')}
              aria-label={`${t('admin.grantCredits')} ${user.name}`}
            >
              <Coins weight="bold" className="w-4 h-4" />
            </button>
            <button
              onClick={() => toggleActive(user)}
              className={`p-2 rounded-lg transition-colors ${
                user.is_active
                  ? 'hover:bg-red-50 dark:hover:bg-red-900/20 text-surface-400 hover:text-red-600'
                  : 'hover:bg-emerald-50 dark:hover:bg-emerald-900/20 text-surface-400 hover:text-emerald-600'
              }`}
              title={user.is_active ? t('admin.deactivate') : t('admin.activate')}
              aria-label={`${user.is_active ? t('admin.deactivate') : t('admin.activate')} ${user.name}`}
            >
              {user.is_active ? <UserMinus weight="bold" className="w-4 h-4" /> : <UserPlus weight="bold" className="w-4 h-4" />}
            </button>
          </div>
        );
      },
    }),
  ], [editingId, editRole, savingRole, editingQuotaId, editQuota, savingQuota, t]);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64" role="status" aria-label={t('common.loading')}>
        <SpinnerGap weight="bold" className="w-8 h-8 text-primary-600 animate-spin" />
      </div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="space-y-6"
      data-testid="admin-users-shell"
      data-surface={surfaceVariant}
    >
      <section className={`overflow-hidden rounded-[28px] border px-6 py-6 shadow-[0_22px_64px_-42px_rgba(15,23,42,0.45)] ${
        isVoid
          ? 'border-slate-800 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_32%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.95))] text-white'
          : 'border-stone-200 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.12),_transparent_38%),linear-gradient(135deg,rgba(255,252,248,0.98),rgba(240,253,250,0.92))] text-surface-900 dark:border-surface-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,rgba(22,26,34,0.98),rgba(31,41,55,0.94))] dark:text-white'
      }`}>
        <div className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
          <div>
            <div className={`mb-3 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-white/80 text-emerald-700 dark:bg-white/10 dark:text-emerald-300'
            }`}>
              <Coins weight="fill" className="h-3.5 w-3.5" />
              {isVoid ? 'Void user ledger' : 'Marble user ledger'}
            </div>

            <div className="flex flex-col gap-4">
              <div>
                <div className="flex flex-wrap items-center gap-3">
                  <h1 className="text-3xl font-black tracking-[-0.04em]">{t('admin.users')}</h1>
                  <span className={`rounded-full px-3 py-1 text-sm font-semibold tabular-nums ${
                    isVoid
                      ? 'bg-white/10 text-white/80'
                      : 'bg-white/85 text-surface-600 dark:bg-white/10 dark:text-white/80'
                  }`}>
                    ({users.length})
                  </span>
                </div>
                <p className={`mt-2 max-w-2xl text-sm leading-6 ${isVoid ? 'text-slate-300' : 'text-surface-600 dark:text-surface-300'}`}>
                  Roles, quotas, credits and lifecycle changes stay in one operator surface without dropping the existing bulk and table flows.
                </p>
              </div>

              <div className="grid gap-3 sm:grid-cols-3">
                <AdminUsersHeroStat
                  label="Active accounts"
                  value={String(activeUsers)}
                  meta={`${users.length - activeUsers} inactive`}
                  isVoid={isVoid}
                  accent
                />
                <AdminUsersHeroStat
                  label="Privileged"
                  value={String(adminUsers)}
                  meta="Admin + superadmin"
                  isVoid={isVoid}
                />
                <AdminUsersHeroStat
                  label="Credit pool"
                  value={String(totalCredits)}
                  meta={`${totalQuota} monthly quota`}
                  isVoid={isVoid}
                />
              </div>

              <div className="relative max-w-md">
                <MagnifyingGlass weight="bold" className={`absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 ${isVoid ? 'text-slate-500' : 'text-surface-400'}`} />
                <input
                  type="text"
                  value={search}
                  onChange={e => setSearch(e.target.value)}
                  placeholder={t('admin.searchUsers')}
                  className={`w-full rounded-2xl border py-3 pl-10 pr-10 text-sm outline-none transition ${
                    isVoid
                      ? 'border-white/10 bg-slate-950/60 text-white placeholder:text-slate-500 focus:border-cyan-500/40'
                      : 'border-white/80 bg-white/85 text-surface-900 placeholder:text-surface-400 focus:border-primary-300 dark:border-white/10 dark:bg-white/[0.06] dark:text-white'
                  }`}
                  aria-label={t('admin.searchUsers')}
                />
                {search && (
                  <button
                    type="button"
                    onClick={() => setSearch('')}
                    aria-label={t('admin.clearSearch')}
                    className={`absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1 transition-colors ${
                      isVoid
                        ? 'text-slate-400 hover:bg-white/10 hover:text-white'
                        : 'text-surface-400 hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-white/10 dark:hover:text-white'
                    }`}
                  >
                    <X weight="bold" className="w-3.5 h-3.5" />
                  </button>
                )}
              </div>
            </div>
          </div>

          <div className={`rounded-[24px] border p-5 ${
            isVoid
              ? 'border-white/10 bg-white/[0.04]'
              : 'border-white/80 bg-white/80 dark:border-white/10 dark:bg-white/[0.04]'
          }`}>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
              Control posture
            </p>
            <div className="mt-4 space-y-3">
              <AdminUsersPanelMetric
                label="Bulk operations"
                value={selectedIds.size ? `${selectedIds.size} selected` : 'Ready'}
                helper="Activate, deactivate, role changes and delete stay available."
                isVoid={isVoid}
              />
              <AdminUsersPanelMetric
                label="Credits + quota"
                value={`${totalQuota}`}
                helper="Quota editing and grant-credit modal remain in place."
                isVoid={isVoid}
              />
              <AdminUsersPanelMetric
                label="Lifecycle"
                value={`${activeUsers}/${users.length || 0}`}
                helper="Active state toggles remain inline on each row."
                isVoid={isVoid}
              />
            </div>
          </div>
        </div>
      </section>

      {/* Bulk Actions Bar */}
      {selectedIds.size > 0 && (
        <div className={`flex flex-wrap items-center gap-3 rounded-[22px] border p-4 ${
          isVoid
            ? 'border-cyan-500/20 bg-cyan-500/10'
            : 'border-primary-200 bg-primary-50/90 dark:border-primary-800 dark:bg-primary-900/20'
        }`}>
          <span className={`text-sm font-medium ${isVoid ? 'text-cyan-100' : 'text-primary-700 dark:text-primary-300'}`}>
            {t('admin.selectedCount', { count: selectedIds.size })}
          </span>
          <select
            value={bulkAction}
            onChange={e => setBulkAction(e.target.value)}
            className={`rounded-xl border px-3 py-2 text-xs outline-none ${
              isVoid
                ? 'border-white/10 bg-slate-950/70 text-white'
                : 'border-white/80 bg-white text-surface-900 dark:border-white/10 dark:bg-surface-950/70 dark:text-white'
            }`}
            aria-label={t('admin.selectAction')}
          >
            <option value="">{t('admin.selectAction')}</option>
            <option value="activate">{t('admin.bulkActivate')}</option>
            <option value="deactivate">{t('admin.bulkDeactivate')}</option>
            <option value="set_role">{t('admin.bulkChangeRole')}</option>
            <option value="delete">{t('admin.bulkDelete')}</option>
          </select>
          {bulkAction === 'set_role' && (
            <select
              value={bulkRole}
              onChange={e => setBulkRole(e.target.value)}
              className={`rounded-xl border px-3 py-2 text-xs outline-none ${
                isVoid
                  ? 'border-white/10 bg-slate-950/70 text-white'
                  : 'border-white/80 bg-white text-surface-900 dark:border-white/10 dark:bg-surface-950/70 dark:text-white'
              }`}
              aria-label={t('admin.editRole')}
            >
              <option value="user">user</option>
              <option value="premium">premium</option>
              <option value="admin">admin</option>
            </select>
          )}
          <button
            onClick={() => setBulkConfirm(true)}
            disabled={!bulkAction || bulkRunning}
            className={`flex items-center gap-1 rounded-xl px-3 py-2 text-xs font-semibold text-white transition disabled:opacity-50 ${
              isVoid ? 'bg-cyan-500 hover:bg-cyan-400' : 'bg-primary-600 hover:bg-primary-700'
            }`}
          >
            {bulkRunning ? <SpinnerGap className="animate-spin" weight="bold" size={14} /> : <Lightning weight="bold" size={14} />}
            {t('admin.bulkApply')}
          </button>
          <button
            onClick={() => setSelectedIds(new Set())}
            className={`text-xs font-medium transition-colors ${isVoid ? 'text-slate-300 hover:text-white' : 'text-surface-500 hover:text-surface-700 dark:text-surface-400 dark:hover:text-white'}`}
          >
            {t('admin.bulkClear')}
          </button>
        </div>
      )}

      <ConfirmDialog
        open={bulkConfirm}
        title={t('admin.bulkAction')}
        message={t('admin.bulkActionConfirm', { action: bulkAction, count: selectedIds.size })}
        variant={bulkAction === 'delete' ? 'danger' : 'default'}
        onConfirm={handleBulkAction}
        onCancel={() => setBulkConfirm(false)}
      />

      {/* Grant Credits Modal */}
      <AnimatePresence>
        {creditUserId && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            className="overflow-hidden"
          >
            <div className={`rounded-[24px] border p-6 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.35)] ${
              isVoid
                ? 'border-slate-800 bg-slate-950/90 text-white'
                : 'border-surface-200 bg-white dark:border-surface-800 dark:bg-surface-950/80'
            }`}>
              <div className="flex items-center justify-between">
                <div>
                  <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
                    Credit adjustment
                  </p>
                  <h3 className="mt-2 text-lg font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">{t('admin.grantCredits')}</h3>
                </div>
                <button
                  onClick={() => setCreditUserId(null)}
                  className={`rounded-lg p-1.5 transition-colors ${isVoid ? 'hover:bg-white/10' : 'hover:bg-surface-100 dark:hover:bg-surface-800'}`}
                  aria-label={t('common.close')}
                >
                  <X weight="bold" className="w-5 h-5 text-surface-400" />
                </button>
              </div>
              <p className={`mt-3 text-sm ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>
                {t('admin.grantingTo')} <strong className="text-surface-900 dark:text-white">{users.find(u => u.id === creditUserId)?.name}</strong>
              </p>
              <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label htmlFor="credit-amount" className={`mb-2 block text-sm font-medium ${isVoid ? 'text-slate-200' : 'text-surface-700 dark:text-surface-300'}`}>{t('admin.amount')}</label>
                  <input
                    id="credit-amount"
                    type="number"
                    min={1}
                    value={creditAmount}
                    onChange={e => setCreditAmount(e.target.value)}
                    className={`w-full rounded-2xl border px-4 py-3 text-sm outline-none transition ${
                      isVoid
                        ? 'border-white/10 bg-slate-900 text-white placeholder:text-slate-500 focus:border-cyan-500/40'
                        : 'border-surface-200 bg-white text-surface-900 focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white'
                    }`}
                    placeholder="10"
                  />
                </div>
                <div>
                  <label htmlFor="credit-description" className={`mb-2 block text-sm font-medium ${isVoid ? 'text-slate-200' : 'text-surface-700 dark:text-surface-300'}`}>{t('admin.description')}</label>
                  <input
                    id="credit-description"
                    type="text"
                    value={creditDesc}
                    onChange={e => setCreditDesc(e.target.value)}
                    className={`w-full rounded-2xl border px-4 py-3 text-sm outline-none transition ${
                      isVoid
                        ? 'border-white/10 bg-slate-900 text-white placeholder:text-slate-500 focus:border-cyan-500/40'
                        : 'border-surface-200 bg-white text-surface-900 focus:border-primary-300 dark:border-surface-700 dark:bg-surface-950 dark:text-white'
                    }`}
                  />
                </div>
              </div>
              <div className="mt-5 flex gap-3">
                <button
                  onClick={handleGrantCredits}
                  disabled={grantingCredits || !creditAmount}
                  className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition disabled:opacity-50 ${
                    isVoid ? 'bg-cyan-500 hover:bg-cyan-400' : 'bg-primary-600 hover:bg-primary-700'
                  }`}
                >
                  {grantingCredits ? <SpinnerGap weight="bold" className="w-4 h-4 animate-spin" /> : <Check weight="bold" className="w-4 h-4" />}
                  {t('admin.grant')}
                </button>
                <button
                  onClick={() => setCreditUserId(null)}
                  className={`rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                    isVoid
                      ? 'border border-white/10 bg-white/[0.04] text-slate-200 hover:bg-white/10'
                      : 'border border-surface-200 bg-surface-50 text-surface-700 hover:bg-surface-100 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200 dark:hover:bg-surface-800'
                  }`}
                >
                  {t('common.cancel')}
                </button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <section className={`rounded-[24px] border p-5 shadow-[0_18px_50px_-38px_rgba(15,23,42,0.18)] ${
        isVoid
          ? 'border-slate-800 bg-slate-950/85'
          : 'border-surface-200 bg-white dark:border-surface-800 dark:bg-surface-950/80'
      }`}>
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-surface-400'}`}>
              Directory
            </p>
            <h2 className="mt-1 text-xl font-semibold tracking-[-0.03em] text-surface-900 dark:text-white">Accounts table</h2>
          </div>
          <div className="flex flex-wrap gap-2">
            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
              isVoid
                ? 'bg-white/10 text-white/80'
                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
            }`}>
              {activeUsers} active
            </span>
            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${
              isVoid
                ? 'bg-cyan-500/10 text-cyan-100'
                : 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300'
            }`}>
              {adminUsers} privileged
            </span>
          </div>
        </div>

        <DataTable
          data={users}
          columns={columns}
          searchValue={debouncedSearch}
          emptyMessage={search ? t('admin.noUsersMatch') : t('admin.noUsersFound')}
          exportFilename="users"
        />
      </section>
    </motion.div>
  );
}

function AdminUsersHeroStat({
  label,
  value,
  meta,
  isVoid,
  accent = false,
}: {
  label: string;
  value: string;
  meta: string;
  isVoid: boolean;
  accent?: boolean;
}) {
  return (
    <div className={`rounded-[22px] border px-4 py-4 ${
      isVoid
        ? accent
          ? 'border-cyan-500/20 bg-cyan-500/10'
          : 'border-white/10 bg-white/[0.04]'
        : accent
        ? 'border-emerald-200 bg-emerald-500/10 dark:border-emerald-900/60'
        : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.04]'
    }`}>
      <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${
        isVoid
          ? accent ? 'text-cyan-100' : 'text-white/45'
          : accent ? 'text-emerald-700 dark:text-emerald-300' : 'text-surface-500 dark:text-white/45'
      }`}>
        {label}
      </p>
      <p className={`mt-3 text-3xl font-black tracking-[-0.04em] ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>
        {value}
      </p>
      <p className={`mt-2 text-xs ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{meta}</p>
    </div>
  );
}

function AdminUsersPanelMetric({
  label,
  value,
  helper,
  isVoid,
}: {
  label: string;
  value: string;
  helper: string;
  isVoid: boolean;
}) {
  return (
    <div className={`rounded-[20px] border px-4 py-3 ${
      isVoid
        ? 'border-white/10 bg-white/[0.03]'
        : 'border-white/80 bg-white/85 dark:border-white/10 dark:bg-white/[0.03]'
    }`}>
      <div className="flex items-center justify-between gap-3">
        <p className={`text-[11px] font-semibold uppercase tracking-[0.18em] ${isVoid ? 'text-white/45' : 'text-surface-500 dark:text-white/45'}`}>
          {label}
        </p>
        <span className={`text-sm font-semibold ${isVoid ? 'text-white' : 'text-surface-900 dark:text-white'}`}>{value}</span>
      </div>
      <p className={`mt-2 text-xs leading-5 ${isVoid ? 'text-slate-300' : 'text-surface-500 dark:text-surface-400'}`}>{helper}</p>
    </div>
  );
}
