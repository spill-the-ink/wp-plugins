import "./global.css";

// Stub wp.media for the labs environment (no real WordPress media library)
if (typeof window.wp === 'undefined') {
  (window as any).wp = {};
}
(window as any).wp.media = function () {
  return {
    _selectCallback: null as (() => void) | null,
    on(event: string, cb: () => void) {
      if (event === 'select') this._selectCallback = cb;
      return this;
    },
    open() {
      const id = prompt('Labs demo: enter a numeric attachment ID:');
      if (id && this._selectCallback) {
        this._selectCallback();
      }
      return this;
    },
    state() {
      return {
        get() {
          return {
            first() {
              return {
                toJSON() { return { id: 0, url: '' }; },
              };
            },
          };
        },
      };
    },
  };
};

// Import the shared settings controller.
// It self-initializes when the DOM is ready (IIFE pattern).
import "../../../tools/shared-settings/shared-settings.js";
