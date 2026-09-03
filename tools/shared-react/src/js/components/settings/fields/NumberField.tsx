import * as React from "react";
import { Input } from "../../ui/input";
import { cn } from "../../../lib/utils";

interface NumberFieldProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'type'> {
  name: string;
  label?: string;
  description?: string;
  value: number;
  min?: number;
  max?: number;
  onChange?: (value: number) => void;
}

const NumberField = React.forwardRef<HTMLInputElement, NumberFieldProps>(
  ({ name, label, description, value, min = 0, max = 1000, onChange, className, ...props }, ref) => {
    return (
      <Input
        ref={ref}
        type="number"
        name={name}
        id={name}
        value={value}
        min={min}
        max={max}
        onChange={(e) => onChange?.(parseInt(e.target.value, 10) || 0)}
        className={cn("small-text", className)}
        {...props}
      />
    );
  },
);
NumberField.displayName = "NumberField";

export { NumberField };
export type { NumberFieldProps };
