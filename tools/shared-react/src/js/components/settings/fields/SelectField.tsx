import * as React from "react";
import { cn } from "../../../lib/utils";

interface SelectOption {
  value: string;
  label: string;
  disabled?: boolean;
}

interface SelectFieldProps {
  name: string;
  label?: string;
  description?: string;
  value: string;
  options: SelectOption[];
  placeholder?: string;
  onChange?: (value: string) => void;
  className?: string;
}

function SelectField({ name, label, description, value, options, placeholder, onChange, className }: SelectFieldProps) {
  return (
    <select
      name={name}
      id={name}
      value={value}
      onChange={(e) => onChange?.(e.target.value)}
      className={cn(
        "flex h-9 w-full rounded-sm border border-input bg-background px-3 py-1.5 text-sm",
        "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
        "disabled:cursor-not-allowed disabled:opacity-50",
        className,
      )}
    >
      {placeholder && (
        <option value="" disabled>
          {placeholder}
        </option>
      )}
      {options.map((option) => (
        <option key={option.value} value={option.value} disabled={option.disabled}>
          {option.label}
        </option>
      ))}
    </select>
  );
}

export { SelectField };
export type { SelectFieldProps, SelectOption };
