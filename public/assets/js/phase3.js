import {api, notify} from './app.js';

const workspaces = [...document.querySelectorAll('[data-organization-module]')];
const dialog = document.querySelector('#organizationDialog');
const form = document.querySelector('#organizationForm');
const fields = document.querySelector('#organizationFields');
const dialogTitle = document.querySelector('#organizationDialogTitle');
const message = document.querySelector('#organizationMessage');
let activeModule = '';
let activeEntity = '';
let cache = {};

const labels = {house: 'Casa', apartment: 'Departamento', lot: 'Lote', warehouse: 'Nave industrial', main: 'Principal', pedestrian: 'Peatonal', vehicle: 'Vehicular', service: 'Servicio'};
const entityTitles = {client: 'Nuevo cliente', location: 'Nuevo lugar', access_point: 'Nuevo punto de acceso', unit: 'Nueva unidad', resident: 'Nuevo residente'};

function element(tag, text, className = '') { const node=document.createElement(tag); if(text!==undefined)node.textContent=text; if(className)node.className=className; return node; }
function section(title, items, columns, entity, statusEntities = []) {
    const card=element('article',undefined,'security-card organization-list');
    const heading=element('div',undefined,'card-heading'); heading.append(element('h3',title),element('span',`${items.length} registros`,'badge neutral')); card.append(heading);
    if(!items.length){card.append(element('p','No hay registros dentro de tu alcance.','muted'));return card;}
    const tableWrap=element('div',undefined,'table-scroll'); const table=element('table'); const head=element('thead'); const headRow=element('tr');
    columns.forEach(column=>headRow.append(element('th',column.label))); if(statusEntities.includes(entity))headRow.append(element('th','Acciones')); head.append(headRow); table.append(head);
    const body=element('tbody'); items.forEach(item=>{const row=element('tr');columns.forEach(column=>{let value=item[column.key]??'—';if(column.map)value=column.map[value]||value;if(column.key==='is_active')value=Number(value)===1?'Activo':'Inactivo';row.append(element('td',String(value)));});if(statusEntities.includes(entity)){const cell=element('td');const button=element('button',Number(item.is_active)===1?'Desactivar':'Reactivar','ghost-button');button.type='button';button.addEventListener('click',()=>changeStatus(entity,item.id,Number(item.is_active)!==1));cell.append(button);row.append(cell);}body.append(row);});
    table.append(body);tableWrap.append(table);card.append(tableWrap);return card;
}

async function fetchItems(path){const payload=await api(path);return payload.data?.items||[];}
async function loadWorkspace(module, force=false){
    const workspace=workspaces.find(node=>node.dataset.organizationModule===module); if(!workspace||(!force&&workspace.dataset.loaded==='1'))return;
    if(module==='usuarios'&&workspace.dataset.canGuards==='1')return;
    const content=workspace.querySelector('[data-organization-content]');content.textContent='Consultando registros…';const statusEntities=(content.dataset.statusEntities||'').split(',').filter(Boolean);
    try{
        const fragment=document.createDocumentFragment();
        if(module==='clientes'){cache.clients=await fetchItems('/organization/clients');fragment.append(section('Clientes',cache.clients,[{key:'code',label:'Código'},{key:'name',label:'Cliente'},{key:'timezone',label:'Zona horaria'},{key:'is_active',label:'Estado'}],'client',statusEntities));}
        if(module==='sitios'){
            [cache.locations,cache.points,cache.units]=await Promise.all([fetchItems('/organization/locations'),fetchItems('/organization/access-points'),fetchItems('/organization/units')]);
            fragment.append(section('Lugares',cache.locations,[{key:'client_name',label:'Cliente'},{key:'code',label:'Código'},{key:'name',label:'Lugar'},{key:'city',label:'Ciudad'},{key:'is_active',label:'Estado'}],'location',statusEntities));
            fragment.append(section('Puntos de acceso',cache.points,[{key:'location_name',label:'Lugar'},{key:'code',label:'Código'},{key:'name',label:'Punto'},{key:'point_type',label:'Tipo',map:labels},{key:'is_active',label:'Estado'}],'access_point',statusEntities));
            fragment.append(section('Unidades',cache.units,[{key:'location_name',label:'Lugar'},{key:'code',label:'Código'},{key:'name',label:'Unidad'},{key:'unit_type',label:'Tipo',map:labels},{key:'is_active',label:'Estado'}],'unit',statusEntities));
        }
        if(module==='usuarios'){[cache.residents,cache.units]=await Promise.all([fetchItems('/organization/residents'),fetchItems('/organization/units')]);fragment.append(section('Residentes',cache.residents,[{key:'full_name',label:'Nombre'},{key:'email',label:'Correo'},{key:'phone',label:'Teléfono'},{key:'units',label:'Unidades'},{key:'is_active',label:'Estado'}],'resident',statusEntities));}
        if(module==='mis_unidades'){cache.units=await fetchItems('/organization/units');fragment.append(section('Unidades relacionadas',cache.units,[{key:'client_name',label:'Cliente'},{key:'location_name',label:'Lugar'},{key:'code',label:'Código'},{key:'name',label:'Unidad'},{key:'unit_type',label:'Tipo',map:labels}],'unit',[]));}
        content.textContent='';content.append(fragment);workspace.dataset.loaded='1';
    }catch(error){content.textContent=error.message;notify(error.message,'error');}
}

function inputField(definition){const label=element('label',definition.label);let control;if(definition.options){control=document.createElement('select');definition.options.forEach(item=>{const option=element('option',item.label);option.value=item.value;control.append(option);});}else{control=document.createElement('input');control.type=definition.type||'text';if(definition.minlength)control.minLength=definition.minlength;}control.name=definition.name;control.required=definition.required!==false;label.append(control);return label;}
function definitions(entity){
    const timezone={name:'timezone',label:'Zona horaria',options:[{value:'America/Mexico_City',label:'America/Mexico_City'},{value:'UTC',label:'UTC'}]};
    const locations=(cache.locations||[]).map(item=>({value:item.id,label:`${item.client_name} · ${item.name}`}));const units=(cache.units||[]).map(item=>({value:item.id,label:`${item.location_name} · ${item.name}`}));const clients=(cache.clients||[]).map(item=>({value:item.id,label:item.name}));
    return {client:[{name:'code',label:'Código'},{name:'name',label:'Nombre'},{name:'legal_name',label:'Razón social',required:false},timezone],location:[{name:'client_id',label:'Cliente',options:clients},{name:'code',label:'Código'},{name:'name',label:'Nombre'},{name:'address_line',label:'Dirección'},{name:'city',label:'Ciudad',required:false},{name:'state',label:'Estado',required:false},{name:'postal_code',label:'Código postal',required:false},timezone],access_point:[{name:'location_id',label:'Lugar',options:locations},{name:'code',label:'Código'},{name:'name',label:'Nombre'},{name:'point_type',label:'Tipo',options:[{value:'main',label:'Principal'},{value:'pedestrian',label:'Peatonal'},{value:'vehicle',label:'Vehicular'},{value:'service',label:'Servicio'}]}],unit:[{name:'location_id',label:'Lugar',options:locations},{name:'code',label:'Código'},{name:'name',label:'Nombre'},{name:'unit_type',label:'Tipo',options:[{value:'house',label:'Casa'},{value:'apartment',label:'Departamento'},{value:'lot',label:'Lote'},{value:'warehouse',label:'Nave industrial'}]}],resident:[{name:'full_name',label:'Nombre completo'},{name:'email',label:'Correo',type:'email'},{name:'phone',label:'Teléfono',required:false},{name:'unit_id',label:'Unidad principal',options:units},{name:'password',label:'Contraseña temporal',type:'password',minlength:12}]}[entity]||[];
}
async function openForm(entity){activeEntity=entity;dialogTitle.textContent=entityTitles[entity]||'Nuevo registro';fields.textContent='';message.textContent='';definitions(entity).forEach(def=>fields.append(inputField(def)));dialog.showModal();}
async function changeStatus(entity,id,isActive){try{await api('/organization/status',{method:'POST',body:JSON.stringify({entity,id,is_active:isActive})});notify('Estado actualizado.');await loadWorkspace(activeModule,true);}catch(error){notify(error.message,'error');}}

document.querySelectorAll('[data-organization-create]').forEach(button=>button.addEventListener('click',()=>openForm(button.dataset.organizationCreate)));
document.querySelector('#closeOrganizationDialog')?.addEventListener('click',()=>dialog.close());
form?.addEventListener('submit',async event=>{event.preventDefault();const submit=form.querySelector('[type=submit]');submit.disabled=true;try{const data=Object.fromEntries(new FormData(form));await api('/organization/create',{method:'POST',body:JSON.stringify({entity:activeEntity,...data})});dialog.close();notify('Registro creado correctamente.');await loadWorkspace(activeModule,true);}catch(error){message.textContent=error.message;message.dataset.type='error';}finally{submit.disabled=false;}});
document.querySelectorAll('[data-view-target]').forEach(link=>link.addEventListener('click',()=>{activeModule=link.dataset.viewTarget;loadWorkspace(activeModule);}));
activeModule=location.hash.slice(1);loadWorkspace(activeModule);
