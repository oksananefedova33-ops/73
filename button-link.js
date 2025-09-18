/*!
 * Модуль "кнопка - ссылка" (linkbtn) — фикс: нечувствительность к регистру типа (Linkbtn/linkbtn),
 * полный мерж данных и перехват отправки на /editor/api.php.
 */
(function(){
  const onReady = fn => (document.readyState==='loading' ? document.addEventListener('DOMContentLoaded',fn) : fn());
  const PALETTE = ['#111827','#1f2937','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ffffff'];
  const toHex = c=>{
    if(!c) return '#000000'; c=String(c).trim();
    if(/^#/.test(c)){ return c.length===4 ? '#'+c.slice(1).split('').map(x=>x+x).join('') : c; }
    const m=c.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i); if(!m) return '#000000';
    return '#'+[m[1],m[2],m[3]].map(n=>Number(n).toString(16).padStart(2,'0')).join('');
  };
  const esc = s=> String(s).replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m]));
  const uid = ()=> 'el_'+Math.random().toString(36).slice(2,9);

  /* ---------- кнопка в тулбаре ---------- */
  function insertToolbarBtn(){
    const topbar = document.querySelector('.topbar') || document.querySelector('#toolbar') || document.querySelector('.editor-toolbar') || document.querySelector('.builder-toolbar');
    const mount = (ref)=>{
      const b=document.createElement('button'); b.type='button'; b.id='btnAddLinkBtn';
      b.textContent='кнопка - ссылка'; b.className = (ref && ref.classList && ref.classList.contains('btn')) ? 'btn' : 'bl-toolbar-button';
      (ref && ref.parentNode) ? ref.parentNode.insertBefore(b, ref.nextSibling) : (topbar||document.body).appendChild(b);
      b.addEventListener('click', addLinkBtn);
    };
    if(topbar){
      const ref = Array.from(topbar.querySelectorAll('button,.btn')).find(x=>/Картинка|Видео|Image|Video/i.test(x.textContent||'')) || topbar.lastElementChild;
      return mount(ref);
    }
    const refBtn = Array.from(document.querySelectorAll('button,.btn,[role="button"]')).find(x=>/Картинка|Видео|Image|Video/i.test(x.textContent||''));
    if(refBtn) mount(refBtn);
  }

  /* ---------- создание элемента ---------- */
  function addLinkBtn(){
    if(typeof window.selectEl==='function' && typeof window.createElement==='function'){
      try{ window.selectEl(window.createElement('linkbtn', {width:30,height:10})); return; }catch(e){}
    }
    createLinkBtn({width:30,height:10});
  }
  function createLinkBtn(opts={}){
    const el=document.createElement('div'); el.className='el linkbtn'; el.dataset.type= opts.datasetType || 'linkbtn'; el.dataset.id=opts.id || uid();
    el.style.left=(opts.left ?? 10)+'%'; el.style.top=(opts.top ?? 10)+'px';
    el.style.width=(opts.width ?? 30)+'%'; el.style.height=(opts.height ?? 10)+'%';
    el.style.zIndex=(opts.z ?? 1); el.style.borderRadius=(opts.radius ?? 12)+'px'; el.style.rotate=(opts.rotate ?? 0)+'deg';
    const a=document.createElement('a');
    a.className='bl-linkbtn bl-anim-'+(opts.anim||'none');
    a.textContent = (opts.text || 'Кнопка').replace(/[📄📦📕📘📗📙🎵🎬🖼️💻💿📝]/g,'');
    // Убираем кавычки из URL при создании
    let cleanUrl = (opts.url || '#').replace(/^['"]|['"]$/g, '');
    a.href = cleanUrl; 
    a.target='_blank'; 
    a.rel='noopener';
    a.style.setProperty('--bl-bg', opts.bg || '#3b82f6');
    a.style.setProperty('--bl-color', opts.color || '#ffffff');
    a.style.setProperty('--bl-radius', (opts.radius ?? 12)+'px');
    a.dataset.anim = (opts.anim || 'none');
    a.addEventListener('click', e=>{ if(!(e.ctrlKey||e.metaKey)) e.preventDefault(); });
    el.appendChild(a);
    const stage = document.querySelector('#stage') || document.querySelector('.stage') || document.querySelector('#editor-canvas') || document.querySelector('.editor-canvas') || document.body;
    stage.appendChild(el);
    try{ if(typeof window.ensureTools==='function') ensureTools(el); }catch(e){}
    try{ if(typeof window.ensureHandle==='function') ensureHandle(el); }catch(e){}
    try{ if(typeof window.attachDragResize==='function') attachDragResize(el); }catch(e){}
    return el;
  }

  /* ---------- универсальные селекторы linkbtn ---------- */
  const Q_LINKBTN = '#stage .el[data-type="linkbtn" i], .stage .el[data-type="linkbtn" i], #stage .el.linkbtn, .stage .el.linkbtn, #stage .el.Linkbtn, .stage .el.Linkbtn';

  /* ---------- сбор из DOM ---------- */
  function collectLinkbtns(){
    const res=[];
    document.querySelectorAll(Q_LINKBTN).forEach(el=>{
      const a = el.querySelector('a'); 
      if(!a) return;
      
      // Получаем стили напрямую из style атрибута И из CSS-переменных
      const styleAttr = a.getAttribute('style') || '';
      const bgMatch = styleAttr.match(/--bl-bg:\s*([^;]+)/);
      const colorMatch = styleAttr.match(/--bl-color:\s*([^;]+)/);
      
      // Fallback на getComputedStyle если не нашли в style
      const cs = getComputedStyle(a);
      const bg = bgMatch ? bgMatch[1].trim() : (cs.getPropertyValue('--bl-bg')||'#3b82f6').trim();
      const color = colorMatch ? colorMatch[1].trim() : (cs.getPropertyValue('--bl-color')||'#ffffff').trim();
      
      // ВАЖНО: убираем лишние кавычки из URL
      let url = a.getAttribute('href') || '';
      // Убираем одинарные и двойные кавычки с начала и конца
      url = url.replace(/^['"]|['"]$/g, '');
      
      res.push({
        id: el.dataset.id || uid(),
        type: 'linkbtn',
        left: parseFloat(el.style.left)||0,
        top: parseInt(el.style.top)||0,
        width: parseFloat(el.style.width)||0,
        height: parseFloat(el.style.height)||0,
        z: parseInt(el.style.zIndex||'1',10) || 1,
        radius: parseInt(el.style.borderRadius||'12',10) || 12,
        rotate: parseFloat(el.style.rotate||'0')||0,
        text: (a.textContent||'Кнопка').replace(/[📄📦📕📘📗📙🎵🎬🖼️💻💿📝]/g,''),
        url: url, // Используем очищенный URL
        bg: bg || '#3b82f6',
        color: color || '#ffffff',
        anim: a.dataset.anim || 'none'
      });
    });
    return res;
  }

  /* ---------- мерж с перезаписью по id ---------- */
  function mergeLinkbtns(base){
    const data = (base && typeof base==='object') ? base : {elements:[]};
    if(!Array.isArray(data.elements)) data.elements=[];
    const idx = new Map();
    data.elements.forEach((e,i)=> idx.set(e && e.id ? e.id : ('#'+i), i));
    collectLinkbtns().forEach(e=>{
      const key = e.id || '';
      if(idx.has(key)){
        const i = idx.get(key);
        data.elements[i] = Object.assign({}, data.elements[i], e, {type:'linkbtn'});
      }else{
        data.elements.push(e);
      }
    });
    return data;
  }

  /* ---------- интеграция с ядром ---------- */
  function extendEditor(){
    const _create = window.createElement;
    const _render = window.renderProps;
    const _gather = window.gatherData;

    if(typeof window.createElement==='function'){
      window.createElement = function(type, opts={}){
        if(/linkbtn/i.test(String(type))) return createLinkBtn(Object.assign({}, opts, {datasetType:type}));
        return _create(type, opts);
      };
    }

    if(typeof window.renderProps==='function'){
      window.renderProps = function(el){
        const t = (el && (el.dataset?.type || [...el.classList].join(' '))) || '';
        if(!/linkbtn/i.test(t)) return _render(el);
        _render(el);

        const props = document.getElementById('props') || document.querySelector('#right,#sidebar,.props,.right-panel'); if(!props) return;
        const a = el.querySelector('a'); if(!a) return;
        const bg = toHex(getComputedStyle(a).getPropertyValue('--bl-bg') || '#3b82f6');
        const fg = toHex(getComputedStyle(a).getPropertyValue('--bl-color') || '#ffffff');
        const radius = parseInt(el.style.borderRadius || '12', 10) || 12;
        const anim = a.dataset.anim || 'none';
        const pal = PALETTE.map(c=>`<div class="sw" data-c="${c}" title="${c}" style="background:${c}"></div>`).join('');
        const box = document.createElement('div');
        box.innerHTML = `
          <div class="row">
            <div><div class="label">Текст кнопки</div><input type="text" id="blText" value="${esc(a.textContent||'Кнопка')}"></div>
            <div><div class="label">URL</div><input type="text" id="blUrl" placeholder="https://..." value="${esc(a.getAttribute('href')||'')}"></div>
          </div>
          <div class="row">
            <div><div class="label">Фон</div><div class="palette" id="blBgPal">${pal}</div><input type="color" id="blBg" value="${bg}"></div>
            <div><div class="label">Цвет текста</div><div class="palette" id="blFgPal">${pal}</div><input type="color" id="blFg" value="${fg}"></div>
          </div>
          <div class="row">
            <div><div class="label">Округление (px)</div><input type="range" id="blRadius" min="0" max="40" step="1" value="${radius}"></div>
            <div><div class="label">Анимация</div>
              <select id="blAnim">
                <option value="none"  ${anim==='none'?'selected':''}>Нет</option>
                <option value="pulse" ${anim==='pulse'?'selected':''}>Пульсация</option>
                <option value="shake" ${anim==='shake'?'selected':''}>Встряхивание</option>
                <option value="fade"  ${anim==='fade'?'selected':''}>Мерцание</option>
                <option value="slide" ${anim==='slide'?'selected':''}>Покачивание</option>
              </select>
            </div>
          </div>`;
        props.appendChild(box);

        box.querySelector('#blText').addEventListener('input', e=> {
  const v = (e.target.value || '').replace(/[📄📦📕📘📗📙🎵🎬🖼️💻💿📝]/g,'');
  a.textContent = v;
});
        box.querySelector('#blUrl').addEventListener('input', e=> a.setAttribute('href', (e.target.value||'').trim()));
        function bindPal(containerSel, inputSel, setter){
          box.querySelectorAll(containerSel+' .sw').forEach(sw=> sw.addEventListener('click', ()=>{ const c=sw.dataset.c; box.querySelector(inputSel).value=c; setter(c); }));
          box.querySelector(inputSel).addEventListener('input', e=> setter(e.target.value));
        }
        bindPal('#blBgPal', '#blBg', c=> a.style.setProperty('--bl-bg', c));
        bindPal('#blFgPal', '#blFg', c=> a.style.setProperty('--bl-color', c));
        box.querySelector('#blRadius').addEventListener('input', e=>{
          const v = parseInt(e.target.value||'0',10)||0;
          el.style.borderRadius = v+'px';
          a.style.setProperty('--bl-radius', v+'px');
        });
        box.querySelector('#blAnim').addEventListener('change', e=>{
          const v = e.target.value;
          a.classList.remove('bl-anim-none','bl-anim-pulse','bl-anim-shake','bl-anim-fade','bl-anim-slide');
          a.classList.add('bl-anim-'+v);
          a.dataset.anim = v;
        });
      };
    }

    // Базовая сборка (если есть у ядра)
    const baseGather = (typeof _gather==='function') ? _gather : ()=>({elements:[]});
    const ours = ()=> mergeLinkbtns(baseGather());

    // Подменяем используемые сборщики
    window.gatherData = ours;
    ['collectData','collectElements','buildData','getDataForSave'].forEach(n=>{
      try{
        const prev = (typeof window[n]==='function') ? window[n] : null;
        window[n] = function(){ return mergeLinkbtns(prev ? prev() : baseGather()); };
      }catch(e){ window[n] = ours; }
    });

    // Перехват отправки на /editor/api.php — подменяем тело на наш merged JSON
    function patchBody(body){
      try{
        if(body instanceof FormData){ body.set('data', JSON.stringify(ours())); return body; }
        if(typeof body==='string'){ try{ const obj=JSON.parse(body); obj.data=ours(); return JSON.stringify(obj);}catch(e){ return body; } }
        if(body && typeof body==='object'){ body.data=ours(); return JSON.stringify(body); }
      }catch(e){}
      return body;
    }
    const _fetch = window.fetch;
    if(typeof _fetch==='function'){
      window.fetch = function(input, init){
        try{
          const url = (typeof input==='string' ? input : (input && input.url) ) || '';
          if(/editor\/api\.php/i.test(String(url)) && init && 'body' in init){ init.body = patchBody(init.body); }
        }catch(e){}
        return _fetch.apply(this, arguments);
      };
    }
    if(window.XMLHttpRequest){
      const _open = XMLHttpRequest.prototype.open, _send = XMLHttpRequest.prototype.send;
      XMLHttpRequest.prototype.open = function(method, url){ this.__isEditorApi = /editor\/api\.php/i.test(String(url||'')); return _open.apply(this, arguments); };
      XMLHttpRequest.prototype.send = function(body){ if(this.__isEditorApi) body = patchBody(body); return _send.call(this, body); };
    }

    // Гидрация после загрузки (на случай если ядро не отрисовало наш тип)
    function hydrate(){
      const cand=[window.pageData,window.PAGE,window.__PAGE__,window.data,window.state,window.__STATE__,window.zrPage];
      for(const d of cand){
        if(d && Array.isArray(d.elements)){
          d.elements.filter(e=>e && /linkbtn/i.test(String(e.type||''))).forEach(e=>{
            if(!document.querySelector(`${Q_LINKBTN}[data-id="${e.id||''}"]`)) createLinkBtn(e);
          });
          break;
        }
      }
    }
    let tries=0; const t=setInterval(()=>{ hydrate(); if(++tries>12) clearInterval(t); }, 300);
  }

  onReady(()=>{ insertToolbarBtn(); extendEditor(); });
})();
