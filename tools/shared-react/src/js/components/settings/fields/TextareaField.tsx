import * as React from "react";
import { Textarea } from "../../ui/textarea";
import { cn } from "../../../lib/utils";

interface TextareaFieldProps extends Omit<React.TextareaHTMLAttributes<HTMLTextAreaElement>, 'onChange'> {
  name: string;
  label?: string;
  description?: string;
  value: string;
  rows?: number;
  onChange?: (value: string) => void;
}

const TextareaField = React.forwardRef<HTMLTextAreaElement, TextareaFieldProps>(
  ({ name, label, description, value, rows = 4, onChange, className, ...props }, ref) => {
    return (
      <Textarea
        ref={ref}
        name={name}
        id={name}
        value={value}
        rows={rows}
        onChange={(e) => onChange?.(e.target.value)}
        className={cn("large-text", className)}
        {...props}
      />
    );
  },
);
TextareaField.displayName = "TextareaField";

export { TextareaField };
export type { TextareaFieldProps };
