/** Own listeners, timers and requests created by the transitional demo engine. */
export function createDemoScope() {
    const controller = new AbortController();
    const timers = new Set<number>();
    const intervals = new Set<number>();
    let disposed = false;

    return {
        listen(target: EventTarget, type: string, listener: EventListener, options: AddEventListenerOptions | boolean = {}) {
            target.addEventListener(type, listener, {
                ...(typeof options === 'boolean' ? { capture: options } : options),
                signal: controller.signal,
            });
        },
        timeout(callback: () => void, delay?: number) {
            if (disposed) return 0;
            const timer = window.setTimeout(() => {
                timers.delete(timer);
                if (!disposed) callback();
            }, delay);
            timers.add(timer);
            return timer;
        },
        interval(callback: () => void, delay?: number) {
            if (disposed) return 0;
            const timer = window.setInterval(callback, delay);
            intervals.add(timer);
            return timer;
        },
        fetch(input: RequestInfo | URL, options: RequestInit = {}) {
            return window.fetch(input, { ...options, signal: controller.signal });
        },
        dispose() {
            disposed = true;
            controller.abort();
            timers.forEach(window.clearTimeout);
            intervals.forEach(window.clearInterval);
        },
    };
}
