import { onBeforeUnmount, onMounted } from 'vue';
import type { Ref } from 'vue';

/**
 * Reveal an element once when it first scrolls into view, then disconnect so it
 * never re-triggers on scroll-up. Adds the `revealed` class; pair it with a
 * `.reveal-row` style. Honours `prefers-reduced-motion` by revealing instantly.
 */
export function useScrollReveal(el: Ref<HTMLElement | null>) {
    let observer: IntersectionObserver | null = null;

    onMounted(() => {
        const reduce = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        if (reduce || !el.value) {
            el.value?.classList.add('revealed');

            return;
        }

        observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer?.disconnect();
                }
            },
            { threshold: 0.1 },
        );

        observer.observe(el.value);
    });

    onBeforeUnmount(() => observer?.disconnect());
}
