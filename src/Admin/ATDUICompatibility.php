<?php

declare(strict_types=1);

namespace RouteBox\Admin;

/** Presentation-only compatibility layer. Provider APIs and provisioning logic remain untouched. */
final class ATDUICompatibility
{
    public static function start(): void { ob_start([self::class, 'rewrite']); }

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
        $html = preg_replace('~href=(["\'])/?\?section=servers\1~i', 'href="/?section=routebox"', $html) ?? $html;
        $html = preg_replace('~action=(["\'])/?\?section=servers\1~i', 'action="/?section=routebox"', $html) ?? $html;
        $html = str_replace('\\n', '', $html);

        $ui = <<<'HTML'
<style id="atd-ui-compatibility">
/* ATD Fluent UI tokens: calm, accessible, semantic accents. */
:root{
  --atd-accent:#4f7cff;--atd-accent-strong:#3d68e8;--atd-accent-soft:rgba(79,124,255,.12);
  --atd-success:#27a878;--atd-warning:#d89b2b;--atd-danger:#d95c67;
  --atd-radius:12px;--atd-radius-sm:9px;
}
:root.light{
  --bg:#f3f6fa;--bg2:#eaf0f7;--card:rgba(255,255,255,.96);--card2:#fff;
  --line:#d9e1eb;--text:#202938;--muted:#637083;
  --primary:var(--atd-accent);--primary2:var(--atd-accent-strong);--cyan:#3d9bd8;
  --shadow:0 14px 40px rgba(31,45,65,.075);
}
html[lang="fa"] body{font-family:Vazirmatn,IRANSansX,IRANSans,Tahoma,"Segoe UI",Arial,sans-serif;letter-spacing:0}
html[lang="fa"] .topbar h1,html[lang="fa"] .section-head h2,html[lang="fa"] .hero h2{letter-spacing:0;font-weight:800}
html[lang="fa"] input,html[lang="fa"] textarea,html[lang="fa"] button,html[lang="fa"] select{font-family:inherit}
:root.light body{background:radial-gradient(900px 520px at 0% -15%,color-mix(in srgb,var(--atd-accent) 8%,transparent),transparent 62%),radial-gradient(800px 500px at 100% 0%,rgba(118,150,255,.055),transparent 60%),var(--bg)}
:root.light .sidebar{background:rgba(248,250,253,.91);border-color:#d9e1eb;box-shadow:8px 0 28px rgba(31,41,55,.035)}
:root.light .nav a{color:#596678}.light .nav a:hover{color:#202938;background:var(--atd-accent-soft)}
:root.light .nav a.active{color:#3458ba;background:linear-gradient(135deg,var(--atd-accent-soft),rgba(79,124,255,.045));border-color:color-mix(in srgb,var(--atd-accent) 18%,transparent);box-shadow:inset 3px 0 0 var(--atd-accent)}
:root.light .hero{background:linear-gradient(135deg,color-mix(in srgb,var(--atd-accent) 7%,#fff),#f8fafc);border-color:#d8e3ef;box-shadow:0 18px 50px rgba(31,41,55,.075)}
:root.light .hero p{color:#637083}:root.light .card,:root.light .stat{box-shadow:0 12px 34px rgba(31,41,55,.055);backdrop-filter:none}
:root.light .card:hover,:root.light .stat:hover{border-color:#cbd7e5}
:root.light input,:root.light textarea,:root.light select{background:#fff;border-color:#ccd6e2;color:#202938}
:root.light input:focus,:root.light textarea:focus,:root.light select:focus{border-color:var(--atd-accent);box-shadow:0 0 0 3px var(--atd-accent-soft)}
:root.light .btn-secondary{background:#fff;border-color:#ccd6e2;color:#263241}:root.light .btn-secondary:hover{background:#f5f8fb;border-color:#b8c6d6}
:root.light .chip{background:rgba(255,255,255,.82);border-color:#d5dee8;color:#637083}:root.light .status{background:#fff;border-color:#d7e0e9}
:root.light .version-box,.light .plan{background:#f8fafc}
.flag{display:inline-flex;align-items:center;justify-content:center;min-width:30px;min-height:22px;font-size:0}
.flag img.atd-country-flag{display:block;width:30px;height:20px;object-fit:cover;border-radius:4px;box-shadow:0 1px 5px rgba(0,0,0,.22)}
.atd-project-placeholder{opacity:.72;cursor:default!important}.atd-server-delete{margin:0}
.atd-pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin:16px 0 2px;flex-wrap:wrap}
.atd-pagination button{min-width:34px;height:34px;border:1px solid var(--line);background:var(--card);color:var(--text);border-radius:9px;cursor:pointer;font-weight:700}.atd-pagination button.active{background:linear-gradient(135deg,var(--primary),var(--primary2));color:#fff;border-color:transparent}.atd-pagination .atd-page-info{color:var(--muted);font-size:11px;margin:0 6px}
.atd-ui-toolbar{display:flex;align-items:center;gap:7px;margin-inline-start:auto;flex-wrap:wrap}.atd-ui-control{height:36px;min-width:36px;padding:0 10px;border:1px solid var(--line);border-radius:10px;background:var(--card);color:var(--text);display:inline-flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;font:600 12px inherit;box-shadow:0 3px 12px rgba(31,45,65,.045)}.atd-ui-control:hover{border-color:color-mix(in srgb,var(--atd-accent) 35%,var(--line));background:var(--atd-accent-soft)}.atd-color-menu{position:absolute;top:44px;inset-inline-end:0;z-index:9999;width:226px;padding:12px;border:1px solid var(--line);border-radius:14px;background:var(--card);box-shadow:0 18px 45px rgba(20,30,50,.16);display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.atd-color-menu[hidden]{display:none}.atd-color{height:40px;border:1px solid var(--line);border-radius:10px;background:var(--card2);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:11px;color:var(--text)}.atd-color i{width:15px;height:15px;border-radius:50%;background:var(--swatch);box-shadow:0 0 0 3px color-mix(in srgb,var(--swatch) 15%,transparent)}.atd-color.active{border-color:var(--swatch);box-shadow:0 0 0 2px color-mix(in srgb,var(--swatch) 18%,transparent)}
@media(max-width:720px){.atd-ui-toolbar{width:100%;margin-top:8px}.atd-ui-control{flex:1}.atd-color-menu{inset-inline-start:0;inset-inline-end:auto}}
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
function applyAccent(key){const a=accents[key]||accents.blue;root.style.setProperty('--atd-accent',a.value);root.style.setProperty('--atd-accent-strong',a.strong);localStorage.setItem('atd-accent',key);document.querySelectorAll('.atd-color').forEach(x=>x.classList.toggle('active',x.dataset.accent===key));}
function setLang(lang){root.setAttribute('lang',lang);localStorage.setItem('atd-lang',lang);document.querySelectorAll('[data-atd-lang]').forEach(b=>b.textContent=lang==='fa'?'EN':'FA');}
function flagCode(value){const chars=Array.from((value||'').trim());if(chars.length===2){const a=chars[0].codePointAt(0),b=chars[1].codePointAt(0);if(a>=127462&&a<=127487&&b>=127462&&b<=127487)return String.fromCharCode(a-127397,b-127397).toLowerCase();}return/^[A-Za-z]{2}$/.test((value||'').trim())?(value||'').trim().toLowerCase():'';}
function addToolbar(){const host=document.querySelector('.topbar .topbar-actions,.topbar .actions,.topbar')||document.body;const wrap=document.createElement('div');wrap.className='atd-ui-toolbar';wrap.style.position='relative';
const lang=document.createElement('button');lang.type='button';lang.className='atd-ui-control';lang.dataset.atdLang='';lang.title='Language';lang.addEventListener('click',()=>setLang(root.getAttribute('lang')==='fa'?'en':'fa'));wrap.appendChild(lang);
const theme=document.createElement('button');theme.type='button';theme.className='atd-ui-control';theme.title='Theme';theme.textContent=root.classList.contains('light')?'☀ Light':'☾ Dark';theme.addEventListener('click',()=>{const light=!root.classList.contains('light');root.classList.toggle('light',light);localStorage.setItem('atd-theme',light?'light':'dark');theme.textContent=light?'☀ Light':'☾ Dark';});wrap.appendChild(theme);
const box=document.createElement('div');box.style.position='relative';const color=document.createElement('button');color.type='button';color.className='atd-ui-control';color.textContent='🎨';color.title='Accent color';const menu=document.createElement('div');menu.className='atd-color-menu';menu.hidden=true;Object.entries(accents).forEach(([key,a])=>{const b=document.createElement('button');b.type='button';b.className='atd-color';b.dataset.accent=key;b.style.setProperty('--swatch',a.value);b.innerHTML='<i aria-hidden="true"></i><span>'+a.name+'</span>';b.addEventListener('click',()=>{applyAccent(key);menu.hidden=true;});menu.appendChild(b);});color.addEventListener('click',()=>menu.hidden=!menu.hidden);box.append(color,menu);wrap.appendChild(box);
const parent=host.closest('.topbar')||host;if(parent===document.body){document.body.prepend(wrap);}else{parent.appendChild(wrap);}}

document.querySelectorAll('.flag').forEach(el=>{const code=flagCode(el.textContent);if(!code)return;const img=document.createElement('img');img.className='atd-country-flag';img.width=30;img.height=20;img.loading='lazy';img.alt=code.toUpperCase()+' flag';img.src='https://flagcdn.com/w40/'+code+'.png';img.onerror=()=>{el.textContent=code.toUpperCase();el.style.fontSize='12px';};el.replaceChildren(img);});
document.querySelectorAll('.hero-actions a').forEach(a=>{if((a.getAttribute('href')||'').includes('section=routebox')){a.textContent='Project Website';a.href='#';a.classList.add('atd-project-placeholder');a.setAttribute('aria-disabled','true');a.addEventListener('click',e=>e.preventDefault());}});

document.querySelectorAll('form').forEach(form=>{const action=form.querySelector('input[name="action"][value="add_server"]');if(!action)return;const addCard=form.closest('.card');if(!addCard||!addCard.parentElement)return;const cards=Array.from(addCard.parentElement.children).filter(n=>n.classList&&n.classList.contains('card'));const listCard=cards.find(card=>{if(card===addCard)return false;if(card.querySelector('.server-list'))return true;const h=card.querySelector('h2');return h&&/server|سرور/i.test(h.textContent||'');});if(listCard)addCard.parentElement.insertBefore(addCard,listCard.nextSibling);});

document.querySelectorAll('.server-list > .server').forEach(row=>{const idInput=row.querySelector('input[name="id"]');const btns=row.querySelector('.btnrow');if(!idInput||!btns||btns.querySelector('.atd-server-delete'))return;const id=idInput.value;const form=document.createElement('form');form.method='post';form.action='/routebox-server-action.php';form.className='atd-server-delete';form.innerHTML='<input type="hidden" name="csrf_token" value="'+(document.querySelector('input[name="csrf_token"]')?.value||'')+'"><input type="hidden" name="action" value="delete_server"><input type="hidden" name="id" value="'+id+'"><button class="btn btn-danger" type="submit">× Delete</button>';form.addEventListener('submit',e=>{if(!confirm('Delete this RouteBox server?'))e.preventDefault();});btns.appendChild(form);});
const renderPagination=(items,perPage,anchor)=>{if(!items.length||items.length<=perPage)return;const controls=document.createElement('div');controls.className='atd-pagination';const pages=Math.ceil(items.length/perPage);let current=1;const render=()=>{items.forEach((item,i)=>{item.style.display=Math.floor(i/perPage)+1===current?'':'none';});controls.replaceChildren();for(let p=1;p<=pages;p++){const b=document.createElement('button');b.type='button';b.textContent=String(p);if(p===current)b.classList.add('active');b.addEventListener('click',()=>{current=p;render();anchor.scrollIntoView({behavior:'smooth',block:'start'});});controls.appendChild(b);}const info=document.createElement('span');info.className='atd-page-info';info.textContent=current+' / '+pages;controls.appendChild(info);};anchor.after(controls);render();};
renderPagination(Array.from(document.querySelectorAll('.server-list > .server')),5,document.querySelector('.server-list'));
const providerSection=new URLSearchParams(location.search).get('section')||'';if(providerSection==='ibsng'||providerSection==='mikrotik'){const serverCards=Array.from(document.querySelectorAll('.card')).filter(card=>card.querySelector('input[name="action"][value="update_server"]')||card.querySelector('input[name="action"][value="delete_server"]'));if(serverCards.length)renderPagination(serverCards,5,serverCards[serverCards.length-1]);}
const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);const nodes=[];while(walker.nextNode())nodes.push(walker.currentNode);nodes.forEach(node=>{const parent=node.parentElement;if(!parent||/^(TEXTAREA|SCRIPT|STYLE|INPUT)$/i.test(parent.tagName))return;if(node.nodeValue&&node.nodeValue.includes('RouteBox Telegram Bot'))node.nodeValue=node.nodeValue.replaceAll('RouteBox Telegram Bot','ATD Panel, server and telegram bot control center');});
const savedTheme=localStorage.getItem('atd-theme');if(savedTheme==='light')root.classList.add('light');const savedAccent=localStorage.getItem('atd-accent')||'blue';applyAccent(savedAccent);setLang(localStorage.getItem('atd-lang')||root.getAttribute('lang')||'en');addToolbar();
})();
</script>
HTML;
        $html = preg_replace('~</body>~i', $ui . '</body>', $html, 1) ?? ($html . $ui);
        foreach ($protected as $key=>$value) $html=str_replace($key,$value,$html);
        return $html;
    }
}
