<?php

declare(strict_types=1);

namespace RouteBox\Admin;

/** Presentation-only layer. Provider APIs, provisioning and business logic are untouched. */
final class ATDUICompatibility
{
    public static function start(): void
    {
        ob_start([self::class, 'rewrite']);
    }

    public static function rewrite(string $html): string
    {
        $protected = [];
        $html = preg_replace_callback('~<(textarea|script|style)\b[^>]*>.*?</\1>~is', static function (array $m) use (&$protected): string {
            $key = '___ATD_PROTECTED_' . count($protected) . '___';
            $protected[$key] = $m[0];
            return $key;
        }, $html) ?? $html;

        $html = str_replace('RouteBox Telegram Bot', 'ATD Panel, server and telegram bot control center', $html);
        $html = str_replace('RouteBox Admin', 'ATD Panel', $html);
        $html = str_replace('\\n', '', $html);
        $html = preg_replace('~href=(["\'])/?\?section=servers\1~i', 'href="/?section=routebox"', $html) ?? $html;
        $html = preg_replace('~action=(["\'])/?\?section=servers\1~i', 'action="/?section=routebox"', $html) ?? $html;

        $ui = <<<'HTML'
<style id="atd-ui-compatibility">
/* ATD semantic UI layer. Existing provider markup remains the source of truth. */
:root{
  --atd-accent:#4f7cff;--atd-accent-strong:#3d68e8;--atd-accent-soft:rgba(79,124,255,.13);
  --atd-focus:rgba(79,124,255,.35);
}
:root.light{
  --bg:#f1f5f9!important;--bg2:#e8eef6!important;--card:rgba(255,255,255,.94)!important;--card2:#fff!important;
  --line:#d5dfeb!important;--text:#172033!important;--muted:#627087!important;
  --shadow:0 18px 50px rgba(20,34,60,.09)!important;
}
/* Accent applies to both themes because the real panel uses --primary/--primary2. */
html,body{transition:background-color .2s ease,color .2s ease}
body{background:radial-gradient(900px 520px at 8% -12%,color-mix(in srgb,var(--atd-accent) 15%,transparent),transparent 62%),radial-gradient(800px 500px at 100% 8%,color-mix(in srgb,var(--atd-accent-strong) 10%,transparent),transparent 60%),var(--bg)!important}
.sidebar{border-color:color-mix(in srgb,var(--atd-accent) 15%,var(--line))!important}
.nav a:hover{background:color-mix(in srgb,var(--atd-accent) 8%,transparent)!important}
.nav a.active{color:var(--text)!important;background:linear-gradient(135deg,color-mix(in srgb,var(--atd-accent) 17%,transparent),color-mix(in srgb,var(--atd-accent-strong) 9%,transparent))!important;border-color:color-mix(in srgb,var(--atd-accent) 25%,var(--line))!important;box-shadow:inset 3px 0 0 var(--atd-accent)!important}
.hero{background:linear-gradient(135deg,color-mix(in srgb,var(--atd-accent) 13%,var(--card2)),color-mix(in srgb,var(--atd-accent-strong) 8%,var(--card)))!important;border-color:color-mix(in srgb,var(--atd-accent) 22%,var(--line))!important}
.btn-primary{background:linear-gradient(135deg,var(--atd-accent),var(--atd-accent-strong))!important;box-shadow:0 10px 25px color-mix(in srgb,var(--atd-accent) 22%,transparent)!important}
.stat-icon,.section-icon{background:color-mix(in srgb,var(--atd-accent) 13%,transparent)!important;color:var(--atd-accent)!important}
.eyebrow{color:var(--atd-accent)!important}
.btn:focus-visible,.icon-button:focus-visible,.nav a:focus-visible,input:focus-visible,textarea:focus-visible{outline-color:var(--atd-focus)!important}
input:focus,textarea:focus,select:focus{border-color:var(--atd-accent)!important;box-shadow:0 0 0 3px var(--atd-accent-soft)!important}
.light .card,.light .stat{box-shadow:0 12px 34px color-mix(in srgb,var(--atd-accent) 5%,rgba(20,34,60,.08))!important}
.light .sidebar{background:color-mix(in srgb,var(--card) 92%,var(--atd-accent) 3%)!important}
.light .hero p{color:var(--muted)!important}
.flag{display:inline-flex;align-items:center;justify-content:center;min-width:30px;min-height:22px;font-size:0}
.flag img.atd-country-flag{display:block;width:30px;height:20px;object-fit:cover;border-radius:4px;box-shadow:0 1px 5px rgba(0,0,0,.22)}
.atd-pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin:16px 0 2px;flex-wrap:wrap}
.atd-pagination button{min-width:34px;height:34px;border:1px solid var(--line);background:var(--card);color:var(--text);border-radius:9px;cursor:pointer;font-weight:700}
.atd-pagination button:hover{border-color:color-mix(in srgb,var(--atd-accent) 35%,var(--line));background:var(--atd-accent-soft)}
.atd-pagination button.active{background:linear-gradient(135deg,var(--atd-accent),var(--atd-accent-strong));color:#fff;border-color:transparent}
.atd-pagination .atd-page-info{color:var(--muted);font-size:11px;margin:0 6px}
.atd-ui-tools{display:flex;align-items:center;gap:7px;position:relative;margin-inline-start:0}
.atd-ui-control{height:40px;min-width:40px;padding:0 10px;border:1px solid var(--line);border-radius:12px;background:var(--card);color:var(--text);display:inline-flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;font:600 12px inherit;box-shadow:0 3px 12px rgba(31,45,65,.045)}
.atd-ui-control:hover{border-color:color-mix(in srgb,var(--atd-accent) 40%,var(--line));background:var(--atd-accent-soft)}
.atd-color-wrap{position:relative}
.atd-color-menu{position:absolute;top:46px;inset-inline-end:0;z-index:9999;width:166px;padding:12px;border:1px solid var(--line);border-radius:14px;background:var(--card2);box-shadow:0 18px 45px rgba(20,30,50,.18);display:grid;grid-template-columns:repeat(3,38px);justify-content:center;gap:10px}
.atd-color-menu[hidden]{display:none}
.atd-color{width:38px;height:38px;padding:0;border:2px solid transparent;border-radius:50%;background:var(--swatch);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
.atd-color:hover{transform:scale(1.08);box-shadow:0 5px 14px color-mix(in srgb,var(--swatch) 30%,transparent)}
.atd-color i{display:none}
.atd-color.active{border-color:var(--card2);box-shadow:0 0 0 2px var(--swatch),0 5px 14px color-mix(in srgb,var(--swatch) 28%,transparent)}
.atd-project-placeholder{opacity:.72;cursor:default!important}
.atd-server-delete{margin:0}
.sidebar-foot .mini-link + .mini-link{display:none!important}
.atd-mobile-menu-btn,.atd-mobile-overlay,.atd-mobile-close{display:none}

@media(max-width:800px){
  body.atd-menu-open{overflow:hidden}
  .sidebar{display:none}
  .sidebar.atd-mobile-open{display:block;position:fixed;top:12px;bottom:12px;inset-inline-start:12px;width:min(80vw,320px);max-width:320px;height:auto;max-height:calc(100vh - 24px);overflow-y:auto;overflow-x:hidden;padding:16px;background:var(--card2);border:1px solid var(--line);border-radius:20px;box-shadow:var(--shadow);backdrop-filter:blur(24px);z-index:10001;box-sizing:border-box}
  .sidebar.atd-mobile-open .brand{display:flex;padding:2px 4px 18px}
  .sidebar.atd-mobile-open .sidebar-foot{display:grid;position:static;margin-top:16px;gap:8px}
  .sidebar.atd-mobile-open .nav{display:grid;grid-template-columns:1fr;gap:4px}
  .sidebar.atd-mobile-open .nav a{font-size:13px;justify-content:flex-start;padding:11px 12px}
  .sidebar.atd-mobile-open .nav svg{width:19px;height:19px}
  .sidebar.atd-mobile-open .atd-nav-sub{display:block}
  .sidebar.atd-mobile-open .atd-nav-subitem{font-size:12px;padding:7px 8px}
  .sidebar.atd-mobile-open .nav-chevron{display:inline-flex}
  .atd-mobile-close{display:grid;place-items:center;position:absolute;top:12px;inset-inline-end:12px;width:34px;height:34px;border:1px solid var(--line);border-radius:10px;background:var(--card);color:var(--text);font-size:22px;line-height:1;cursor:pointer;z-index:2}
  .atd-mobile-overlay{display:block;position:fixed;inset:0;background:rgba(3,7,18,.48);backdrop-filter:blur(2px);z-index:10000}
  .atd-mobile-overlay[hidden]{display:none}
  .atd-mobile-menu-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:40px;padding:0 11px;border:1px solid var(--line);border-radius:12px;background:var(--card);color:var(--text);cursor:pointer;font:700 12px inherit}
  .atd-mobile-menu-btn svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
  .main{padding:18px 14px 34px}
  .topbar{align-items:flex-start;gap:10px;margin-bottom:18px}
  .topbar h1{font-size:24px}
  .top-actions{gap:6px}
  .top-actions .chip.keep{display:none}
  .atd-ui-control{height:38px;min-width:38px}
}
@media(max-width:420px){
  .topbar{flex-wrap:wrap}
  .topbar>div:first-child{min-width:0;flex:1}
  .top-actions{margin-inline-start:auto}
  .atd-mobile-menu-btn{width:40px;padding:0;font-size:0}
  .atd-mobile-menu-btn svg{width:19px;height:19px}
  .hero{padding:18px}
  .hero-row{align-items:flex-start;flex-direction:column}
  .grid,.plans,.preview-grid{grid-template-columns:1fr}
}
@media(max-width:720px){.atd-ui-tools{margin-inline-start:0}.atd-color-menu{inset-inline-start:0;inset-inline-end:auto}}
</style>
<script>
(function(){
'use strict';
const root=document.documentElement;
const accents={
 blue:{name:'Blue',value:'#4f7cff',strong:'#3d68e8'},
 indigo:{name:'Indigo',value:'#6657d9',strong:'#5547c3'},
 emerald:{name:'Emerald',value:'#2f9e78',strong:'#238361'},
 cyan:{name:'Cyan',value:'#258fb8',strong:'#19779d'},
 amber:{name:'Amber',value:'#c38a2b',strong:'#a8731f'},
 rose:{name:'Rose',value:'#c85b72',strong:'#ad465e'}
};
function applyAccent(key){
 const a=accents[key]||accents.blue;
 root.style.setProperty('--atd-accent',a.value);
 root.style.setProperty('--atd-accent-strong',a.strong);
 /* The legacy panel uses these variables everywhere. This is the missing link that made the previous palette only affect a few controls. */
 root.style.setProperty('--primary',a.value);
 root.style.setProperty('--primary2',a.strong);
 root.style.setProperty('--cyan',a.value);
 root.style.setProperty('--atd-accent-soft',a.value+'22');
 root.style.setProperty('--atd-focus',a.value+'55');
 localStorage.setItem('atd-accent',key);
 document.querySelectorAll('.atd-color').forEach(x=>x.classList.toggle('active',x.dataset.accent===key));
}
function languageSwitch(){
 const next=root.getAttribute('lang')==='fa'?'en':'fa';
 const url=new URL(location.href);
 url.searchParams.set('lang',next);
 /* Use the same server-side language mechanism as the existing sidebar switch. */
 location.href=url.pathname+'?'+url.searchParams.toString();
}
function flagCode(value){
 const chars=Array.from((value||'').trim());
 if(chars.length===2){const a=chars[0].codePointAt(0),b=chars[1].codePointAt(0);if(a>=127462&&a<=127487&&b>=127462&&b<=127487)return String.fromCharCode(a-127397,b-127397).toLowerCase();}
 return/^[A-Za-z]{2}$/.test((value||'').trim())?(value||'').trim().toLowerCase():'';
}
function localizeSidebar(){
 if(root.getAttribute('lang')!=='fa')return;
 const labels={
  'Dashboard':'داشبورد','Telegram Bot':'ربات تلگرام','Routebox Servers':'سرورهای RouteBox','RouteBox Servers':'سرورهای RouteBox',
  'IBSng Servers':'سرورهای IBSng','MikroTik WireGuard':'سرورهای MikroTik WireGuard','MikroTik WireGuard Servers':'سرورهای MikroTik WireGuard',
  'Usage Guides':'راهنمای استفاده','Plans':'پلن‌ها','Provider Guide':'راهنمای سرویس','Users':'کاربران',
  'Payment Settings':'تنظیمات پرداخت','Security':'امنیت','Updates':'به‌روزرسانی'
 };
 document.querySelectorAll('.sidebar .nav span').forEach(el=>{const text=(el.textContent||'').trim();if(labels[text])el.textContent=labels[text];});
}
function addTopTools(){
 const actions=document.querySelector('.top-actions');
 if(!actions||actions.querySelector('.atd-ui-tools'))return;
 const tools=document.createElement('div');tools.className='atd-ui-tools';
 const lang=document.createElement('button');lang.type='button';lang.className='atd-ui-control';lang.textContent=root.getAttribute('lang')==='fa'?'EN':'FA';lang.title=root.getAttribute('lang')==='fa'?'English':'فارسی';lang.setAttribute('aria-label',lang.title);lang.addEventListener('click',languageSwitch);
 tools.appendChild(lang);
 const wrap=document.createElement('div');wrap.className='atd-color-wrap';
 const color=document.createElement('button');color.type='button';color.className='atd-ui-control';color.textContent='🎨';color.title='Accent color';color.setAttribute('aria-label','Accent color');
 const menu=document.createElement('div');menu.className='atd-color-menu';menu.hidden=true;
 Object.entries(accents).forEach(([key,a])=>{const b=document.createElement('button');b.type='button';b.className='atd-color';b.dataset.accent=key;b.style.setProperty('--swatch',a.value);b.setAttribute('aria-label',a.name);b.title=a.name;b.innerHTML='<i aria-hidden="true"></i>';b.addEventListener('click',()=>{applyAccent(key);menu.hidden=true;});menu.appendChild(b);});
 color.addEventListener('click',e=>{e.stopPropagation();menu.hidden=!menu.hidden;});wrap.append(color,menu);tools.appendChild(wrap);
 /* Put language + palette immediately beside the existing, working theme button. */
 const theme=actions.querySelector('#themeBtn');
 if(theme)actions.insertBefore(tools,theme);else actions.appendChild(tools);
 document.addEventListener('click',e=>{if(!wrap.contains(e.target))menu.hidden=true;});
}
function addMobileMenu(){
 const topbar=document.querySelector('.topbar');
 const sidebar=document.querySelector('.sidebar');
 if(!topbar||!sidebar||topbar.querySelector('.atd-mobile-menu-btn'))return;
 const button=document.createElement('button');
 button.type='button';button.className='atd-mobile-menu-btn';
 const fa=root.getAttribute('lang')==='fa';
 button.setAttribute('aria-label',fa?'باز کردن منو':'Open menu');
 button.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>'+(fa?'منو':'Menu')+'</span>';
 const close=document.createElement('button');
 close.type='button';close.className='atd-mobile-close';close.setAttribute('aria-label',fa?'بستن منو':'Close menu');close.textContent='×';
 sidebar.insertBefore(close,sidebar.firstChild);
 const overlay=document.createElement('div');overlay.className='atd-mobile-overlay';overlay.hidden=true;
 document.body.appendChild(overlay);
 const openMenu=()=>{sidebar.classList.add('atd-mobile-open');overlay.hidden=false;document.body.classList.add('atd-menu-open');button.setAttribute('aria-expanded','true');};
 const closeMenu=()=>{sidebar.classList.remove('atd-mobile-open');overlay.hidden=true;document.body.classList.remove('atd-menu-open');button.setAttribute('aria-expanded','false');};
 button.setAttribute('aria-expanded','false');button.addEventListener('click',openMenu);close.addEventListener('click',closeMenu);overlay.addEventListener('click',closeMenu);document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu();});
 topbar.insertBefore(button,topbar.firstChild);
}

document.querySelectorAll('.flag').forEach(el=>{const code=flagCode(el.textContent);if(!code)return;const img=document.createElement('img');img.className='atd-country-flag';img.width=30;img.height=20;img.loading='lazy';img.alt=code.toUpperCase()+' flag';img.src='https://flagcdn.com/w40/'+code+'.png';img.onerror=()=>{el.textContent=code.toUpperCase();el.style.fontSize='12px';};el.replaceChildren(img);});

document.querySelectorAll('.hero-actions a').forEach(a=>{if((a.getAttribute('href')||'').includes('section=routebox')){a.textContent='Project Website';a.href='#';a.classList.add('atd-project-placeholder');a.setAttribute('aria-disabled','true');a.addEventListener('click',e=>e.preventDefault());}});

document.querySelectorAll('form').forEach(form=>{const action=form.querySelector('input[name="action"][value="add_server"]');if(!action)return;const addCard=form.closest('.card');if(!addCard||!addCard.parentElement)return;const cards=Array.from(addCard.parentElement.children).filter(n=>n.classList&&n.classList.contains('card'));const listCard=cards.find(card=>{if(card===addCard)return false;if(card.querySelector('.server-list'))return true;const h=card.querySelector('h2');return h&&/server|سرور/i.test(h.textContent||'');});if(listCard)addCard.parentElement.insertBefore(addCard,listCard.nextSibling);});

document.querySelectorAll('.server-list > .server').forEach(row=>{const idInput=row.querySelector('input[name="id"]');const btns=row.querySelector('.btnrow');if(!idInput||!btns||btns.querySelector('.atd-server-delete'))return;const id=idInput.value;const form=document.createElement('form');form.method='post';form.action='/routebox-server-action.php';form.className='atd-server-delete';form.innerHTML='<input type="hidden" name="csrf_token" value="'+(document.querySelector('input[name="csrf_token"]')?.value||'')+'"><input type="hidden" name="action" value="delete_server"><input type="hidden" name="id" value="'+id+'"><button class="btn btn-danger" type="submit">× Delete</button>';form.addEventListener('submit',e=>{if(!confirm('Delete this RouteBox server?'))e.preventDefault();});btns.appendChild(form);});

function renderPagination(items,perPage,anchor){if(!items.length||items.length<=perPage||!anchor)return;const old=anchor.parentElement?.querySelector('.atd-pagination');if(old)old.remove();const controls=document.createElement('div');controls.className='atd-pagination';const pages=Math.ceil(items.length/perPage);let current=1;const render=()=>{items.forEach((item,i)=>{item.style.display=Math.floor(i/perPage)+1===current?'':'none';});controls.replaceChildren();for(let p=1;p<=pages;p++){const b=document.createElement('button');b.type='button';b.textContent=String(p);if(p===current)b.classList.add('active');b.addEventListener('click',()=>{current=p;render();anchor.scrollIntoView({behavior:'smooth',block:'start'});});controls.appendChild(b);}const info=document.createElement('span');info.className='atd-page-info';info.textContent=current+' / '+pages;controls.appendChild(info);};anchor.after(controls);render();}
renderPagination(Array.from(document.querySelectorAll('.server-list > .server')),5,document.querySelector('.server-list'));
const section=new URLSearchParams(location.search).get('section')||'';
if(section==='ibsng'||section==='mikrotik'){const cards=Array.from(document.querySelectorAll('.card')).filter(card=>card.querySelector('input[name="action"][value="update_server"]')||card.querySelector('input[name="action"][value="delete_server"]'));if(cards.length>5)renderPagination(cards,5,cards[0].parentElement);}

/* Keep the real panel theme switch as the single source of truth. */
const savedTheme=localStorage.getItem('rbt-theme');if(savedTheme==='light')root.classList.add('light');
const savedAccent=localStorage.getItem('atd-accent')||'blue';applyAccent(savedAccent);localizeSidebar();addTopTools();addMobileMenu();
})();
</script>
HTML;

        $html = preg_replace('~</body>~i', $ui . '</body>', $html, 1) ?? ($html . $ui);
        foreach ($protected as $key=>$value) {
            $html = str_replace($key, $value, $html);
        }
        return $html;
    }
}
