import * as React from "react";
import { Input } from "../../ui/input";
import { cn } from "../../../lib/utils";

interface TextFieldProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'onChange'> {
  name: string;
  label?: string;
  description?: string;
  value: string;
  onChange?: (value: string) => void;
}

const TextField = React.forwardRef<HTMLInputElement, TextFieldProps>(
  ({ name, label, description, value, onChange, className, ...props }, ref) => {
    return (
      <Input
        ref={ref}
        type="text"
        name={name}
        id={name}
        value={value}
        onChange={(e) => onChange?.(e.target.value)}
        className={cn("regular-text", className)}
        {...props}
      />
    );
  },
);
TextField.displayName = "TextField";

export { TextField };
export type { TextFieldProps };
