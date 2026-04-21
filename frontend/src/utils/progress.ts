// Global progress bar — zero React, pure DOM for maximum performance
let activeRequests = 0;
let showTimer: ReturnType<typeof setTimeout> | null = null;
let hideTimer: ReturnType<typeof setTimeout> | null = null;

function getEl(): HTMLElement | null {
  return document.getElementById('global-progress');
}

export function startProgress() {
  activeRequests++;
  if (hideTimer) {
    clearTimeout(hideTimer);
    hideTimer = null;
  }
  // Debounce: only show bar if request takes >100ms
  if (!showTimer) {
    showTimer = setTimeout(() => {
      const el = getEl();
      if (el && activeRequests > 0) {
        el.classList.remove('done');
        el.classList.add('active');
      }
      showTimer = null;
    }, 100);
  }
}

export function doneProgress() {
  activeRequests = Math.max(0, activeRequests - 1);
  if (activeRequests === 0) {
    if (showTimer) {
      clearTimeout(showTimer);
      showTimer = null;
    }
    const el = getEl();
    if (el) {
      el.classList.remove('active');
      el.classList.add('done');
      hideTimer = setTimeout(() => {
        el.classList.remove('done');
        hideTimer = null;
      }, 400);
    }
  }
}
