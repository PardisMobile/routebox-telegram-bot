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
.flag{display:inline-flex;align-items:center;justify-content:center;min-width:30px;min-height:22px;font-size:0}
.flag img.atd-country-flag{display:block;width:30px;height:20px;object-fit:cover;border-radius:4px;box-shadow:0 1px 5px rgba(0,0,0,.22)}
.atd-project-placeholder{opacity:.72;cursor:default!important}
.atd-pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:16px;flex-wrap:wrap}
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

  const paginate=(selector,perPage)=>{const items=Array.from(document.querySelectorAll(selector));if(items.length<=perPage)return;const list=items[0].parentElement;if(!list)return;const controls=document.createElement('div');controls.className='atd-pagination';list.after(controls);const pages=Math.ceil(items.length/perPage);let current=1;const render=()=>{items.forEach((item,i)=>{item.style.display=Math.floor(i/perPage)+1===current?'':'none';});controls.replaceChildren();for(let p=1;p<=pages;p++){const b=document.createElement('button');b.type='button';b.textContent=String(p);if(p===current)b.classList.add('active');b.addEventListener('click',()=>{current=p;render();window.scrollTo({top:list.getBoundingClientRect().top+window.scrollY-80,behavior:'smooth'});});controls.appendChild(b);}const info=document.createElement('span');info.className='atd-page-info';info.textContent=current+' / '+pages;controls.appendChild(info);};render();};
  paginate('.server-list > .server',10);

  const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);const nodes=[];while(walker.nextNode())nodes.push(walker.currentNode);nodes.forEach((node)=>{const parent=node.parentElement;if(!parent||/^(TEXTAREA|SCRIPT|STYLE|INPUT)$/i.test(parent.tagName))return;if(node.nodeValue&&node.nodeValue.includes('RouteBox Telegram Bot'))node.nodeValue=node.nodeValue.replaceAll('RouteBox Telegram Bot','ATD Panel, server and telegram bot control center');});
})();
</script>
HTML;
        $html = preg_replace('~</body>~i', $ui . '</body>', $html, 1) ?? ($html . $ui);
        foreach ($protected as $key=>$value) $html=str_replace($key,$value,$html);
        return $html;
    }
}
