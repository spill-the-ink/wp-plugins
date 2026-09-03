import * as React from "react";
import { useMediaPicker } from "../../../hooks/useMediaPicker";
import { Button } from "../../ui/button";
import { cn } from "../../../lib/utils";

interface MediaFieldProps {
  name: string;
  label?: string;
  description?: string;
  value: number;
  onChange?: (attachmentId: number) => void;
  className?: string;
}

function MediaField({ name, label, description, value, onChange, className }: MediaFieldProps) {
  const { openMediaPicker, attachmentUrl, loading } = useMediaPicker({
    onSelect: (attachment) => {
      onChange?.(attachment.id);
    },
  });

  const previewUrl = attachmentUrl ?? (value > 0 ? null : null);

  return (
    <div className={cn("wps-settings__field--media", className)}>
      <input type="hidden" name={name} value={value || 0} />
      {previewUrl && (
        <img
          src={previewUrl}
          alt=""
          className="wps-settings__media-preview"
          style={{ maxWidth: 200, height: "auto", display: "block", margin: "6px 0" }}
        />
      )}
      {value > 0 && !previewUrl && (
        <p className="wps-settings__media-id">Attachment #{value}</p>
      )}
      <Button type="button" variant="secondary" size="sm" onClick={openMediaPicker} disabled={loading}>
        {value > 0 ? "Replace image" : "Select image"}
      </Button>
      {value > 0 && (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => onChange?.(0)}
          className="ml-2"
        >
          Remove
        </Button>
      )}
      {description && <p className="wps-settings__field-description">{description}</p>}
    </div>
  );
}

export { MediaField };
export type { MediaFieldProps };
