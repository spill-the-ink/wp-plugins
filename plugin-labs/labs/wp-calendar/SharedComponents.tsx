import { useState } from "react";
import {
  SettingsForm,
  SettingsSection,
  SettingsField,
  TextField,
  SelectField,
  CheckboxField,
  Button,
} from "@shared";
import "@shared/styles/settings.css";

/**
 * Sandbox for the shared React components (`tools/shared-react`).
 *
 * Live-imports the production components via the `@shared` alias so changes made
 * in `tools/shared-react` show up here immediately during development.
 */
export default function SharedComponents() {
  const [title, setTitle] = useState("Configure the shared form");
  const [severity, setSeverity] = useState("warning");
  const [enabled, setEnabled] = useState(true);
  const [clicks, setClicks] = useState(0);

  return (
    <SettingsForm optionsName="wps_shared_demo" title="Shared Components" description="Live demo of tools/shared-react components.">
      <SettingsSection id="fields" title="Fields">
        <SettingsField id="title" label="Title" description="A TextField from @shared.">
          <TextField name="title" value={title} onChange={setTitle} />
        </SettingsField>

        <SettingsField id="severity" label="Severity" description="A SelectField from @shared.">
          <SelectField
            name="severity"
            value={severity}
            onChange={setSeverity}
            options={[
              { value: "info", label: "Info" },
              { value: "warning", label: "Warning" },
              { value: "critical", label: "Critical" },
            ]}
          />
        </SettingsField>

        <SettingsField id="enabled" label="Enabled">
          <CheckboxField
            name="enabled"
            label="Enable notifications"
            description="A CheckboxField from @shared."
            checked={enabled}
            onChange={setEnabled}
          />
        </SettingsField>
      </SettingsSection>

      <SettingsSection id="actions" title="Actions">
        <Button variant="primary" onClick={() => setClicks((c) => c + 1)}>
          Clicked {clicks} time{clicks === 1 ? "" : "s"}
        </Button>
      </SettingsSection>
    </SettingsForm>
  );
}
