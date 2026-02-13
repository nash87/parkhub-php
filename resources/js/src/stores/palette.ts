import { create } from "zustand";
import { persist } from "zustand/middleware";

export interface ColorPalette {
  id: string;
  name: string;
  light: {
    primary: string;
    secondary: string;
    accent: string;
    bg: string;
    surface: string;
  };
  dark: {
    primary: string;
    secondary: string;
    accent: string;
    bg: string;
    surface: string;
  };
}

export const PALETTES: ColorPalette[] = [
  {
    id: "default-amber",
    name: "palette.defaultAmber",
    light: { primary: "#F59E0B", secondary: "#6366F1", accent: "#0EA5E9", bg: "#F9FAFB", surface: "#FFFFFF" },
    dark: { primary: "#F59E0B", secondary: "#6366F1", accent: "#0EA5E9", bg: "#030712", surface: "#111827" },
  },
  {
    id: "solarized",
    name: "palette.solarized",
    light: { primary: "#268BD2", secondary: "#2AA198", accent: "#B58900", bg: "#FDF6E3", surface: "#EEE8D5" },
    dark: { primary: "#268BD2", secondary: "#2AA198", accent: "#B58900", bg: "#002B36", surface: "#073642" },
  },
  {
    id: "dracula",
    name: "palette.dracula",
    light: { primary: "#BD93F9", secondary: "#FF79C6", accent: "#50FA7B", bg: "#F8F8F2", surface: "#FFFFFF" },
    dark: { primary: "#BD93F9", secondary: "#FF79C6", accent: "#50FA7B", bg: "#282A36", surface: "#44475A" },
  },
  {
    id: "nord",
    name: "palette.nord",
    light: { primary: "#5E81AC", secondary: "#88C0D0", accent: "#81A1C1", bg: "#ECEFF4", surface: "#E5E9F0" },
    dark: { primary: "#88C0D0", secondary: "#81A1C1", accent: "#5E81AC", bg: "#2E3440", surface: "#3B4252" },
  },
  {
    id: "gruvbox",
    name: "palette.gruvbox",
    light: { primary: "#D79921", secondary: "#689D6A", accent: "#D65D0E", bg: "#FBF1C7", surface: "#EBDBB2" },
    dark: { primary: "#D79921", secondary: "#689D6A", accent: "#D65D0E", bg: "#282828", surface: "#3C3836" },
  },
  {
    id: "catppuccin",
    name: "palette.catppuccin",
    light: { primary: "#8839EF", secondary: "#EA76CB", accent: "#179299", bg: "#EFF1F5", surface: "#E6E9EF" },
    dark: { primary: "#CBA6F7", secondary: "#F5C2E7", accent: "#94E2D5", bg: "#1E1E2E", surface: "#313244" },
  },
  {
    id: "tokyo-night",
    name: "palette.tokyoNight",
    light: { primary: "#2E7DE9", secondary: "#9854F1", accent: "#587539", bg: "#D5D6DB", surface: "#E9E9EC" },
    dark: { primary: "#7AA2F7", secondary: "#BB9AF7", accent: "#9ECE6A", bg: "#1A1B26", surface: "#24283B" },
  },
  {
    id: "one-dark",
    name: "palette.oneDark",
    light: { primary: "#4078F2", secondary: "#A626A4", accent: "#50A14F", bg: "#FAFAFA", surface: "#F0F0F0" },
    dark: { primary: "#61AFEF", secondary: "#C678DD", accent: "#98C379", bg: "#282C34", surface: "#2C313C" },
  },
  {
    id: "rose-pine",
    name: "palette.rosePine",
    light: { primary: "#907AA9", secondary: "#D7827E", accent: "#56949F", bg: "#FAF4ED", surface: "#FFFAF3" },
    dark: { primary: "#C4A7E7", secondary: "#EBBCBA", accent: "#9CCFD8", bg: "#191724", surface: "#1F1D2E" },
  },
  {
    id: "everforest",
    name: "palette.everforest",
    light: { primary: "#8DA101", secondary: "#35A77C", accent: "#F57D26", bg: "#FDF6E3", surface: "#F4F0D9" },
    dark: { primary: "#A7C080", secondary: "#83C092", accent: "#E69875", bg: "#2D353B", surface: "#343F44" },
  },
];

interface PaletteStore {
  paletteId: string;
  setPalette: (id: string) => void;
}

export const usePalette = create<PaletteStore>()(
  persist(
    (set) => ({
      paletteId: "default-amber",
      setPalette: (id) => set({ paletteId: id }),
    }),
    { name: "parkhub-palette" }
  )
);

function hexToRgb(hex: string): [number, number, number] {
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return [r, g, b];
}

function rgbToHsl(r: number, g: number, b: number): [number, number, number] {
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  let h = 0, s = 0;
  const l = (max + min) / 2;
  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
      case g: h = ((b - r) / d + 2) / 6; break;
      case b: h = ((r - g) / d + 4) / 6; break;
    }
  }
  return [h, s, l];
}

function hslToRgb(h: number, s: number, l: number): [number, number, number] {
  if (s === 0) {
    const v = Math.round(l * 255);
    return [v, v, v];
  }
  const hue2rgb = (p: number, q: number, t: number) => {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1/6) return p + (q - p) * 6 * t;
    if (t < 1/2) return q;
    if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
    return p;
  };
  const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
  const p = 2 * l - q;
  return [
    Math.round(hue2rgb(p, q, h + 1/3) * 255),
    Math.round(hue2rgb(p, q, h) * 255),
    Math.round(hue2rgb(p, q, h - 1/3) * 255),
  ];
}

function generateShades(hex: string): Record<string, string> {
  const [r, g, b] = hexToRgb(hex);
  const [h, s] = rgbToHsl(r, g, b);
  
  // shade level -> lightness
  const shadeMap: Record<number, number> = {
    50: 0.96,
    100: 0.92,
    200: 0.84,
    300: 0.72,
    400: 0.60,
    500: 0.48,
    600: 0.40,
    700: 0.32,
    800: 0.26,
    900: 0.20,
    950: 0.14,
  };
  
  const result: Record<string, string> = {};
  for (const [shade, lightness] of Object.entries(shadeMap)) {
    const [sr, sg, sb] = hslToRgb(h, s, lightness);
    result[shade] = `${sr} ${sg} ${sb}`;
  }
  return result;
}

export function applyPalette(paletteId: string, isDark: boolean) {
  const palette = PALETTES.find((p) => p.id === paletteId) || PALETTES[0];
  const colors = isDark ? palette.dark : palette.light;
  const root = document.documentElement;
  
  // Generate full shade range from primary color
  const shades = generateShades(colors.primary);
  for (const [shade, rgb] of Object.entries(shades)) {
    root.style.setProperty(`--color-primary-${shade}`, rgb);
  }
  
  const toRgbStr = (hex: string) => {
    const [r, g, b] = hexToRgb(hex);
    return `${r} ${g} ${b}`;
  };
  
  root.style.setProperty("--color-primary", toRgbStr(colors.primary));
  root.style.setProperty("--color-secondary", toRgbStr(colors.secondary));
  root.style.setProperty("--color-accent", toRgbStr(colors.accent));
  root.style.setProperty("--color-palette-bg", colors.bg);
  root.style.setProperty("--color-palette-surface", colors.surface);
}
