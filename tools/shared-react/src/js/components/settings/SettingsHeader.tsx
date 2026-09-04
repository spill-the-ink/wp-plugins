import * as React from "react";
import { cn } from "../../lib/utils";

interface SettingsHeaderProps extends React.FormHTMLAttributes<HTMLFormElement> {
  optionsName: string;
  title?: string;
  description?: string;
}

const SettingsHeader = React.forwardRef<HTMLFormElement, SettingsHeaderProps>(({ optionsName, title, description, className, children, ...props }, ref) => {
  return (
    <div className={cn('wp-polyfill-settings', className)}>
      {title && <h1 className='wp-polyfill-settings__title'>{title}</h1>}
      {description && <p className='wp-polyfill-settings__description'>{description}</p>}
    </div>
  );
});
SettingsHeader.displayName = 'SettingsForm';



export { SettingsHeader };
export type { SettingsFormProps };
