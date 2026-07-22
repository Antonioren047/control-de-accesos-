(() => {
    const key = 'vigilancia_theme';
    const stored = () => { try { return localStorage.getItem(key) || 'auto'; } catch { return 'auto'; } };
    const apply = (preference = stored()) => {
        document.documentElement.dataset.theme = preference === 'auto'
            ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : preference;
        const select=document.querySelector('#guardThemeSelect');if(select)select.value=preference;
    };
    apply();
    matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => {
        if (stored() === 'auto') apply();
    });

    addEventListener('DOMContentLoaded', () => {
        const portal = document.querySelector('.guard-portal');
        const sidebar = document.querySelector('.guard-portal-sidebar');
        const themeSelect=document.querySelector('#guardThemeSelect');
        if(themeSelect){themeSelect.value=stored();themeSelect.addEventListener('change',()=>{try{localStorage.setItem(key,themeSelect.value)}catch{}apply(themeSelect.value);window.SiteUI?.toast(`Tema ${themeSelect.options[themeSelect.selectedIndex].text.toLowerCase()} aplicado.`,'success','Apariencia actualizada')});}
        if (!portal || !sidebar) return;

        const button = document.createElement('button');
        button.className = 'guard-collapse';
        button.type = 'button';
        button.textContent = '\u2039';
        sidebar.append(button);

        const update = collapsed => {
            portal.classList.toggle('sidebar-collapsed', collapsed);
            button.setAttribute('aria-expanded', String(!collapsed));
            button.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Colapsar menú');
        };
        update(innerWidth > 760 && (()=>{try{return localStorage.getItem('vigilancia_guard_sidebar')==='collapsed'}catch{return false}})());
        button.addEventListener('click', () => {
            const collapsed = !portal.classList.contains('sidebar-collapsed');
            try{localStorage.setItem('vigilancia_guard_sidebar', collapsed ? 'collapsed' : 'expanded')}catch{}
            update(collapsed);
        });
        addEventListener('resize', () => {
            update(innerWidth > 760 && (()=>{try{return localStorage.getItem('vigilancia_guard_sidebar')==='collapsed'}catch{return false}})());
        });
    });
})();
