(() => {
  const icons = {
    home:'<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10.5V20h13v-9.5M9.5 20v-6h5v6"/>',
    clients:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h5M7 13h10M7 16h7"/>',
    locations:'<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
    units:'<path d="M3 10 12 3l9 7M5 9v12h14V9M9 21v-7h6v7"/>',
    users:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    operation:'<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
    attendance:'<path d="m4 12 5 5L20 6"/>',
    visits:'<path d="M4 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M18 8h4M20 6v4"/>',
    providers:'<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M3 12h18M9 12v2h6v-2"/>',
    events:'<path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v4M12 17h.01"/>',
    rounds:'<path d="M5 5h10a4 4 0 0 1 4 4v1M19 19H9a4 4 0 0 1-4-4v-1"/><path d="m16 7 3 3 3-3M8 17l-3-3-3 3"/>',
    sync:'<path d="M20 7h-5V2M4 17h5v5"/><path d="M5.5 9a7 7 0 0 1 11.7-3L20 7M4 17l2.8 1a7 7 0 0 0 11.7-3"/>',
    reports:'<path d="M4 3h12l4 4v14H4z"/><path d="M16 3v5h5M8 13h8M8 17h6"/>',
    settings:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.09A1.7 1.7 0 0 0 9 19.36a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15 1.7 1.7 0 0 0 3.09 14H3v-4h.09A1.7 1.7 0 0 0 4.64 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63h.01A1.7 1.7 0 0 0 10 3.09V3h4v.09A1.7 1.7 0 0 0 15 4.64a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9v.01A1.7 1.7 0 0 0 20.91 10H21v4h-.09A1.7 1.7 0 0 0 19.4 15Z"/>',
    audit:'<path d="M9 11l2 2 4-4"/><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
    maintenance:'<path d="m14.7 6.3 3-3a4 4 0 0 1-5 5L5 16l-2 5 5-2 7.7-7.7a4 4 0 0 1 5-5l-3 3-3-3Z"/>',
    activity:'<path d="M3 12h4l2-6 4 12 2-6h6"/>',
    profile:'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    security:'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
    permissions:'<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4z"/><path d="m15 17 2 2 4-5"/>',
    api:'<path d="m8 9-4 3 4 3M16 9l4 3-4 3M14 5l-4 14"/>',
    camera:'<path d="M4 7h4l2-3h4l2 3h4v13H4z"/><circle cx="12" cy="13" r="4"/>',
    video:'<rect x="3" y="5" width="14" height="14" rx="2"/><path d="m17 10 4-2v8l-4-2"/>',
    qr:'<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h3v3h-3zM19 14h2v7h-4M14 19v2"/>',
    add:'<path d="M12 5v14M5 12h14"/>', edit:'<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
    delete:'<path d="M3 6h18M8 6V3h8v3M19 6l-1 15H6L5 6M10 11v5M14 11v5"/>',
    restore:'<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>', refresh:'<path d="M20 11a8 8 0 1 0 2 5M20 4v7h-7"/>',
    save:'<path d="M4 4h14l2 2v14H4z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/>', close:'<path d="M6 6l12 12M18 6 6 18"/>',
    logout:'<path d="M10 17l5-5-5-5M15 12H3M14 3h7v18h-7"/>', check:'<path d="m4 12 5 5L20 6"/>',
    start:'<path d="m8 5 11 7-11 7Z"/>', finish:'<path d="M5 4h3v16H5zM10 5h9v9h-9z"/>',
    download:'<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>', share:'<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/>',
    comment:'<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/>',
    pin:'<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M15 4l3 3M14 9l2 2"/>',
    print:'<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/>',
    eye:'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    action:'<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>'
  };
  const moduleIcons={inicio:'home',clientes:'clients',sitios:'locations',mis_unidades:'units',usuarios:'users',turnos:'clock',turno:'clock',operacion:'operation',asistencias:'attendance',visitas:'visits',proveedores:'providers',eventos:'events',recorridos:'rounds',supervisiones:'audit',sincronizacion:'sync',reportes:'reports',configuracion:'settings',auditoria:'audit',mantenimiento:'maintenance',mi_actividad:'activity',perfil:'profile',seguridad:'security',permisos:'permissions',api:'api',novedades:'comment',salida:'logout'};
  const svg=name=>`<span class="ui-icon" aria-hidden="true"><svg viewBox="0 0 24 24">${icons[name]||icons.action}</svg></span>`;
  const normalize=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().toLowerCase();
  const nodes=(root,selector)=>[...(root.matches?.(selector)?[root]:[]),...(root.querySelectorAll?.(selector)||[])];
  function actionIcon(button){
    if(button.matches('[data-capture],[data-p8-photo],#takePhoto,#phase8TakePhoto,#phase9Capture,#p9Photo'))return'camera';
    if(button.matches('[data-p8-video],#phase8RecordVideo'))return'video';
    if(button.matches('[data-scan-access],#scanQr,#scanCloseQr'))return'qr';
    if(button.matches('[data-organization-create],[data-workforce-create],[data-phase7-create],#newTypeSave'))return'add';
    if(button.matches('[data-phase3-refresh],[data-phase5-refresh],[data-phase6-refresh],[data-phase7-refresh],[data-phase8-refresh],[data-phase9-refresh],[data-phase11-refresh],#dashboardRefresh,#refreshSessions'))return'refresh';
    if(button.matches('[data-save-type],#p9SavePin,#p9SaveComment'))return'save';
    if(button.matches('[data-remove-evidence]'))return'close';
    if(button.matches('#togglePassword'))return'eye';
    const text=normalize(button.textContent);
    const rules=[[/fotograf|evidencia/,'camera'],[/grabar|video/,'video'],[/escanear|codigo qr|\bqr\b/,'qr'],[/eliminar|revocar|cancelar visita/,'delete'],[/restaurar|regenerar/,'restore'],[/editar|modificar/,'edit'],[/actualizar|sincronizar|recargar/,'refresh'],[/nuevo|nueva|crear|agregar/,'add'],[/guardar|registrar entrada|registrar incidencia|registrar novedad|instalar/,'save'],[/descargar/,'download'],[/imprimir/,'print'],[/compartir/,'share'],[/comentario|comentar/,'comment'],[/pin/,'pin'],[/iniciar sesion|acceso operativo|iniciar|validar/,'start'],[/finalizar|cerrar turno|registrar salida/,'finish'],[/salir/,'logout'],[/aceptar|confirmar|continuar/,'check'],[/cerrar|cancelar|quitar/,'close']];
    return rules.find(([pattern])=>pattern.test(text))?.[1]||'';
  }
  function decorateButton(button){
    if(button.dataset.uiMapped||button.matches('.notification-button,.guard-notification-bell,.notification-item,.guard-notification,[aria-label="Colapsar menú"],[aria-label="Abrir menú"],[aria-label="Cerrar menú"]'))return;
    const name=actionIcon(button);if(!name)return;
    button.dataset.uiMapped='1';button.dataset.uiIcon=name;button.classList.add('ui-action');
    if(!button.querySelector('.ui-icon'))button.insertAdjacentHTML('afterbegin',svg(name));
  }
  function replaceSlot(slot,name){if(!slot||slot.dataset.uiMapped)return;slot.dataset.uiMapped='1';slot.classList.add('ui-nav-icon');slot.innerHTML=svg(name);}
  function decorateModules(root=document){
    nodes(root,'[data-view-target]').forEach(item=>replaceSlot(item.querySelector(':scope > span'),moduleIcons[item.dataset.viewTarget]||'action'));
    nodes(root,'[data-guard-view]').forEach(item=>replaceSlot(item.querySelector(':scope > span'),moduleIcons[item.dataset.guardView]||'action'));
    nodes(root,'[data-open-module]').forEach(item=>replaceSlot(item.querySelector(':scope > span'),moduleIcons[item.dataset.openModule]||'action'));
    nodes(root,'.app-view[data-view] .module-icon,.module-icon').forEach(slot=>replaceSlot(slot,moduleIcons[slot.closest('.app-view')?.dataset.view]||'action'));
    nodes(root,'.guard-view[data-guard-section] h2').forEach(title=>{if(title.querySelector('.page-title-icon'))return;const name=moduleIcons[title.closest('.guard-view').dataset.guardSection]||'operation';title.insertAdjacentHTML('afterbegin',`<span class="page-title-icon">${svg(name)}</span>`)});
  }
  function decorateMedia(root=document){nodes(root,'img[data-camera-preview],#preview,.phase8-evidence img,.phase9-evidence img').forEach(node=>node.classList.add('ui-evidence-preview'));nodes(root,'video[data-camera-video],#camera,#phase8CaptureVideo,#phase9Video,.phase8-evidence video').forEach(node=>node.classList.add('ui-camera-view'))}
  function decorate(root=document){decorateModules(root);nodes(root,'button,.submit,.ghost-button,.primary-button,.back-link,.toolbar a').forEach(decorateButton);decorateMedia(root)}
  addEventListener('DOMContentLoaded',()=>{decorate();new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>{if(node.nodeType===1)decorate(node)}))).observe(document.body,{childList:true,subtree:true})});
  window.SiteIcons={svg,decorate,moduleIcons};
})();
