import { useState, useCallback } from 'react';

interface MediaPickerOptions {
  onSelect: (attachment: { id: number; url: string; title: string }) => void;
  multiple?: boolean;
  libraryType?: string;
}

interface UseMediaPickerReturn {
  openMediaPicker: () => void;
  attachmentUrl: string | null;
  loading: boolean;
}

declare global {
  interface Window {
    wp?: {
      media: (options: Record<string, unknown>) => {
        on: (event: string, callback: (attachment: Record<string, unknown>) => void) => void;
        open: () => void;
      };
    };
  }
}

export function useMediaPicker(options: MediaPickerOptions): UseMediaPickerReturn {
  const { onSelect, multiple = false, libraryType = 'image' } = options;
  const [attachmentUrl, setAttachmentUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const openMediaPicker = useCallback(() => {
    if (!window.wp?.media) {
      console.warn('WordPress media library not available');
      return;
    }

    setLoading(true);

    const mediaFrame = window.wp.media({
      title: 'Select Image',
      button: { text: 'Select' },
      multiple,
      library: { type: libraryType },
    });

    mediaFrame.on('select', () => {
      const selection = mediaFrame.state().get('selection');
      if (selection && selection.length > 0) {
        const attachment = selection.first().toJSON();
        onSelect({
          id: attachment.id as number,
          url: attachment.url as string,
          title: attachment.title as string,
        });
        setAttachmentUrl(attachment.url as string);
      }
      setLoading(false);
    });

    mediaFrame.on('close', () => {
      setLoading(false);
    });

    mediaFrame.open();
  }, [onSelect, multiple, libraryType]);

  return { openMediaPicker, attachmentUrl, loading };
}
