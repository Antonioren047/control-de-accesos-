const KEY = 'vigilancia_theme';

export function initTheme(onChange = null) {
    const select = document.querySelector('#themeSelect');
    const apply = value => {
        const resolved = value === 'auto'
            ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : value;
        document.documentElement.dataset.theme = resolved;
        if (select) select.value = value;
    };
    const configured = document.documentElement.dataset.theme;
    const saved = configured && configured !== 'auto' ? configured : (localStorage.getItem(KEY) || 'auto');
    apply(saved);
    select?.addEventListener('change', async () => {
        localStorage.setItem(KEY, select.value);
        apply(select.value);
        if (onChange) await onChange(select.value);
    });
    matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => {
        if ((localStorage.getItem(KEY) || 'auto') === 'auto') apply('auto');
    });
}
