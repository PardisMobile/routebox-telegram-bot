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
/* Fluent-inspired light mode: soft neutrals, restrained blue accent, clear elevation. */
:root.light{
  --bg:#f3f5f8;
  --bg2:#edf1f6;
  --card:rgba(255,255,255,.94);
  --card2:#ffffff;
  --line:#d9e1ea;
  --text:#1f2937;
  --muted:#5f6b7a;
  --primary:#0067c0;
  --primary2:#0078d4;
  --cyan:#0078d4;
  --shadow:0 14px 40px rgba(31,41,55,.08);
}
html[lang="fa"] body{font-family:Vazirmatn,IRANSansX,IRANSans,Tahoma,"Segoe UI",Arial,sans-serif;letter-spacing:0}
html[lang="fa"] .topbar h1,html[lang="fa"] .section-head h2,html[lang="fa"] .hero h2{letter-spacing:0;font-weight:800}
html[lang="fa"] input,html[lang="fa"] textarea,html[lang="fa"] button,html[lang="fa"] select{font-family:inherit}
:root.light body{background:radial-gradient(900px 520px at 0% -15%,rgba(0,120,212,.08),transparent 62%),radial-gradient(800px 500px at 100% 0%,rgba(91,140,255,.06),transparent 60%),var(--bg)}
:root.light .sidebar{background:rgba(248,250,252,.90);border-color:#d9e1ea;box-shadow:8px 0 28px rgba(31,41,55,.035)}
:root.light .nav a{color:#596678}
:root.light .nav a:hover{color:#1f2937;background:rgba(0,103,192,.055)}
:root.light .nav a.active{color:#0b4f87;background:linear-gradient(135deg,rgba(0,103,192,.10),rgba(0,120,212,.055));border-color:rgba(0,103,192,.16);box-shadow:inset 3px 0 0 #0067c0}
:root.light .hero{background:linear-gradient(135deg,#eef6ff,#f7f9fc);border-color:#d8e5f2;box-shadow:0 18px 50px rgba(31,41,55,.08)}
:root.light .hero p{color:#5f6b7a}
:root.light .card,:root.light .stat{box-shadow:0 12px 34px rgba(31,41,55,.055);backdrop-filter:none}
:root.light .card:hover,:root.light .stat:hover{border-color:#cbd7e5}
:root.light input,:root.light textarea,:root.light select{background:#fff;border-color:#ccd6e2;color:#1f2937}
:root.light input:focus,:root.light textarea:focus,:root.light select:focus{border-color:#0078d4;box-shadow:0 0 0 3px rgba(0,120,212,.12)}
:root.light .btn-secondary{background:#fff;border-color:#ccd6e2;color:#263241}
:root.light .btn-secondary:hover{background:#f5f8fb;border-color:#b8c6d6}
:root.light .chip{background:rgba(255,255,255,.82);border-color:#d5dee8;color:#5f6b7a}
:root.light .status{background:#fff;border-color:#d7e0e9}
:root.light .version-box,.light .plan{background:#f8fafc}
:root.light .atd-nav-sub{border-color:#d5dee8}
:root.light .atd-pagination button{background:#fff;border-color:#ccd6e2}
:root.light .atd-pagination button:hover{background:#f3f7fb;border-color:#b9c8d8}
.flag{display:inline-flex;align-items:center;justify-content:center;min-width:30px;min-height:22px;font-size:0}
.flag img.atd-country-flag{display:block;width:30px;height:20px;object-fit:cover;border-radius:4px;box-shadow:0 1px 5px rgba(0,0,0,.22)}
.atd-project-placeholder{opacity:.72;cursor:default!important}
.atd-pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin:16px 0 2px;flex-wrap:wrap}
.atd-pagination button{min-width:34px;height:34px;border:1px solid var(--line);background:var(--card);color:var(--text);border-radius:9px;cursor:pointer;font-weight:700}
.atd-pagination button.active{background:linear-gradient(135deg,var(--primary),var(--primary2));color:#fff;border-color:transparent}
.atd-pagination .atd-page-info{color:var(--muted);font-size:11px;margin:0 6px}
.atd-server-delete{margin:0}
</style>
<script>
(function(){
  'use strict';
  const flagCode=(value)=>{const chars=Array.from((value||'').trim());if(chars.length===2){const a=chars[0].codePointAt(0),b=chars[1].codePointAt(0);if(a>=127462&&a<=127487&&b>=127462&&b<=127487)return String.fromCharCode(a-127397,b-127397).toLowerCase();}return/^[A-Za-z]{2}$/.test((value||'').trim())?(value||'').trim().toLowerCase():'';};

  document.querySelectorAll('.flag').forEach((el)=>{const code=flagCode(el.textContent);if(!code)return;const img=document.createElement('img');img.className='atd-country-flag';img.width=30;img.height=20;img.loading='lazy';img.alt=code.toUpperCase()+' flag';img.src='https://flagcdn.com/w40/'+code+'.png';img.onerror=()=>{el.textContent=code.toUpperCase();el.style.fontSize='12px';};el.replaceChildren(img);});

  document.querySelectorAll('.hero-actions a').forEach((a)=>{if((a.getAttribute('href')||'').includes('section=routebox')){a.textContent='Project Website';a.href='#';a.classList.add('atd-project-placeholder');a.setAttribute('aria-disabled','true');a.addEventListener('click',(e)=>e.preventDefault());}});

  // Add Server always follows the server list card. Presentation only.
  document.querySelectorAll('form').forEach((form)=>{const action=form.querySelector('input[name="action"][value="add_server"]');if(!action)return;const addCard=form.closest('.card');if(!addCard||!addCard.parentElement)return;const cards=Array.from(addCard.parentElement.children).filter((n)=>n.classList&&n.classList.contains('card'));const listCard=cards.find((card)=>{if(card===addCard)return false;if(card.querySelector('.server-list'))return true;const h=card.querySelector('h2');return h&&/server|سرور/i.test(h.textContent||'');});if(listCard)addCard.parentElement.insertBefore(addCard,listCard.nextSibling);});

  // RouteBox has a real delete endpoint already; expose it without changing provider logic.
  document.querySelectorAll('.server-list > .server').forEach((row)=>{const idInput=row.querySelector('input[name="id"]');const btns=row.querySelector('.btnrow');if(!idInput||!btns||btns.querySelector('.atd-server-delete'))return;const id=idInput.value;const form=document.createElement('form');form.method='post';form.action='/routebox-server-action.php';form.className='atd-server-delete';form.innerHTML='<input type="hidden" name="csrf_token" value="'+(document.querySelector('input[name="csrf_token"]')?.value||'')+'"><input type="hidden" name="action" value="delete_server"><input type="hidden" name="id" value="'+id+'"><button class="btn btn-danger" type="submit">× Delete</button>';form.addEventListener('submit',(e)=>{if(!confirm('Delete this RouteBox server?'))e.preventDefault();});btns.appendChild(form);});

  const renderPagination=(items,perPage,anchor)=>{if(!items.length||items.length<=perPage)return;const controls=document.createElement('div');controls.className='atd-pagination';const pages=Math.ceil(items.length/perPage);let current=1;const render=()=>{items.forEach((item,i)=>{item.style.display=Math.floor(i/perPage)+1===current?'':'none';});controls.replaceChildren();for(let p=1;p<=pages;p++){const b=document.createElement('button');b.type='button';b.textContent=String(p);if(p===current)b.classList.add('active');b.addEventListener('click',()=>{current=p;render();const top=anchor.getBoundingClientRect().top+window.scrollY-80;window.scrollTo({top,behavior:'smooth'});});controls.appendChild(b);}const info=document.createElement('span');info.className='atd-page-info';info.textContent=current+' / '+pages;controls.appendChild(info);};anchor.after(controls);render();};

  // Every provider server list is intentionally capped at five visible servers per page.
  renderPagination(Array.from(document.querySelectorAll('.server-list > .server')),5,document.querySelector('.server-list'));
  const providerSection=new URLSearchParams(location.search).get('section')||'';
  if(providerSection==='ibsng'||providerSection==='mikrotik'){
    const serverCards=Array.from(document.querySelectorAll('.card')).filter((card)=>card.querySelector('input[name="action"][value="update_server"]')||card.querySelector('input[name="action"][value="delete_server"]'));
    if(serverCards.length){renderPagination(serverCards,5,serverCards[serverCards.length-1]);}
  }

  // Keep the compatibility layer presentation-only: no provider API, repository or provisioning changes here.
  const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);const nodes=[];while(walker.nextNode())nodes.push(walker.currentNode);nodes.forEach((node)=>{const parent=node.parentElement;if(!parent||/^(TEXTAREA|SCRIPT|STYLE|INPUT)$/i.test(parent.tagName))return;if(node.nodeValue&&node.nodeValue.includes('RouteBox Telegram Bot'))node.nodeValue=node.nodeValue.replaceAll('RouteBox Telegram Bot','ATD Panel, server and telegram bot control center');});
})();
</script>
HTML;
        $html = preg_replace('~</body>~i', $ui . '</body>', $html, 1) ?? ($html . $ui);
        foreach ($protected as $key=>$value) $html=str_replace($key,$value,$html);
        return $html;
    }
}
