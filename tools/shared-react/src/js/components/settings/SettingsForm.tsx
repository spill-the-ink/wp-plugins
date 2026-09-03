import * as React from "react";
import { cn } from "../../lib/utils";

interface SettingsFormProps extends React.FormHTMLAttributes<HTMLFormElement> {
  optionsName: string;
  title?: string;
  description?: string;
}

const SettingsForm = React.forwardRef<HTMLFormElement, SettingsFormProps>(
  ({ optionsName, title, description, className, children, ...props }, ref) => {
    return (
      <div className={cn("wps-settings", className)}>
        {title && <h1 className="wps-settings__title">{title}</h1>}
        {description && <p className="wps-settings__description">{description}</p>}
        <form action="options.php" method="post" ref={ref} {...props}>
          <input type="hidden" name="option_page" value={optionsName} />
          <input type="hidden" name="action" value="update" />
          {wpSettingsNonce(optionsName)}
          <div className="wps-settings__form">{children}</div>
        </form>
      </div>
    );
  },
);
SettingsForm.displayName = "SettingsForm";

function wpSettingsNonce(_optionPage: string): React.ReactNode {
  return null;
}

interface SettingsSectionProps {
  id: string;
  title?: string;
  description?: string;
  className?: string;
  children: React.ReactNode;
}

function SettingsSection({ id, title, description, className, children }: SettingsSectionProps) {
  return (
    <div className={cn("wps-settings__section", className)} id={`wps-section-${id}`}>
      {title && <h2 className="wps-settings__section-title">{title}</h2>}
      {description && (
        <p className="wps-settings__section-description">{description}</p>
      )}
      <div className="wps-settings__section-fields">{children}</div>
    </div>
  );
}

interface SettingsFieldProps {
  id: string;
  label: string;
  description?: string;
  className?: string;
  children: React.ReactNode;
}

function SettingsField({ id, label, description, className, children }: SettingsFieldProps) {
  return (
    <div className={cn("wps-settings__field", className)} id={`wps-field-${id}`}>
      <label className="wps-settings__field-label" htmlFor={id}>
        {label}
      </label>
      <div className="wps-settings__field-input">{children}</div>
      {description && <p className="wps-settings__field-description">{description}</p>}
    </div>
  );
}

export { SettingsForm, SettingsSection, SettingsField };
export type { SettingsFormProps, SettingsSectionProps, SettingsFieldProps };
