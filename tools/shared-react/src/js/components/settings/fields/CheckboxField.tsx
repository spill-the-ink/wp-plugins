import * as React from "react";
import { Checkbox } from "../../ui/checkbox";
import { cn } from "../../../lib/utils";

interface CheckboxFieldProps {
  name: string;
  label: string;
  description?: string;
  checked: boolean;
  onChange?: (checked: boolean) => void;
  className?: string;
}

function CheckboxField({ name, label, description, checked, onChange, className }: CheckboxFieldProps) {
  return (
    <div className={cn("wp-polyfill-settings__field--checkbox", className)}>
      <label className="wp-polyfill-settings__checkbox-label" htmlFor={name}>
        <Checkbox
          id={name}
          name={name}
          checked={checked}
          onCheckedChange={(value) => onChange?.(value === true)}
        />
        <span>{label}</span>
      </label>
      {description && <p className="wp-polyfill-settings__field-description">{description}</p>}
    </div>
  );
}

export { CheckboxField };
export type { CheckboxFieldProps };
