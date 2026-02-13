import { useBranding } from "../context/branding-hook";

interface ParkHubLogoProps {
  size?: "sm" | "md" | "lg";
  showText?: boolean;
  className?: string;
}

const sizes = {
  sm: { icon: "w-8 h-8", text: "text-lg" },
  md: { icon: "w-10 h-10", text: "text-xl" },
  lg: { icon: "w-14 h-14", text: "text-3xl" },
};

export function ParkHubLogo({ size = "md", showText = true, className = "" }: ParkHubLogoProps) {
  const { branding } = useBranding();

  if (branding.logo_url) {
    return (
      <div className={`flex items-center gap-3 ${className}`}>
        <img src={branding.logo_url} alt={branding.company_name} className={`${sizes[size].icon} object-contain`} />
        {showText && <span className={`${sizes[size].text} font-bold text-gray-900 dark:text-white`}>{branding.company_name}</span>}
      </div>
    );
  }

  return (
    <div className={`flex items-center gap-3 ${className}`}>
      <img src="/icon.svg" alt="ParkHub" className={`${sizes[size].icon} object-contain rounded-xl`} />
      {showText && <span className={`${sizes[size].text} font-bold text-gray-900 dark:text-white`}>{branding.company_name}</span>}
    </div>
  );
}
