/**
 * @param {string} selector
 * @param {function(HTMLElement): void|Promise<void>} init
 * @param {HTMLElement} baseElement
 */
function initElements(selector, init, baseElement = document.documentElement) {
    /** @type {HTMLElement[]} */
    const registeredElements = [];

    /** @param {HTMLElement} element */
    const safeInit = (element) => {
        if (registeredElements.includes(element)) return;

        registeredElements.push(element);

        let initResponse = undefined;
        try {
            initResponse = init(element);
        } catch (e) {
            console.error('Element initialization failed', e);
            return;
        }

        if (initResponse instanceof Promise) {
            initResponse.catch((e) => {
                console.error('Element initialization failed', e);
            });
        }
    };

    const observer = new MutationObserver((mutations) => {
        let hasNewElements = false;

        mutationsLoop:
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node instanceof Element) {
                    hasNewElements = true;
                    break mutationsLoop;
                }
            }
        }

        if (hasNewElements) {
            baseElement.querySelectorAll(selector)
                .forEach((element) => safeInit(element));
        }
    });
    observer.observe(baseElement, {
        childList: true,
        subtree: true,
    });

    baseElement.querySelectorAll(selector)
        .forEach((element) => safeInit(element));
}
