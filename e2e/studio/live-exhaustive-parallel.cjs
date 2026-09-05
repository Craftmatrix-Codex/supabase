const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');

const BASE_URL = (process.env.LIVE_STUDIO_URL || 'https://go-alpha.craftmatrix.org').replace(/\/$/, '');
const PROJECT = process.env.LIVE_STUDIO_PROJECT || 'default';
const START_URL = `${BASE_URL}/project/${encodeURIComponent(PROJECT)}`;
const OUTPUT = process.env.E2E_OUTPUT || '/tmp/live-alpha-e2e';
const MAX_ROUTES = Number(process.env.E2E_MAX_ROUTES || 180);
const MAX_CONTROLS_PER_ROUTE = Number(process.env.E2E_MAX_CONTROLS_PER_ROUTE || 10);
const ROUTE_WORKERS = Number(process.env.E2E_ROUTE_WORKERS || 4);
const CLICK_WORKERS = Number(process.env.E2E_CLICK_WORKERS || 8);
const NAV_TIMEOUT = Number(process.env.E2E_NAV_TIMEOUT || 30000);
const SETTLE_MS = Number(process.env.E2E_SETTLE_MS || 700);

if (!process.env.E2E_USER || !process.env.E2E_PASS) throw new Error('E2E_USER and E2E_PASS are required at runtime');
fs.mkdirSync(path.join(OUTPUT, 'screenshots'), { recursive: true });

const destructive = /\b(delete|drop|truncate|destroy|remove|restore|reset|revoke|rotate|disable|enable|save|create|insert|update|publish|deploy|migrate|execute|run query|send|invite|upload|add new|new function|new table|logout|sign out|disconnect|confirm)\b/i;
const ignoredHref = /^(javascript:|mailto:|tel:|blob:|data:)/i;
const assetPath = /\.(?:js|css|map|png|jpg|jpeg|gif|svg|ico|woff2?|ttf)(?:\?|$)/i;
const issueLine = /\b(application error|something went wrong|failed to fetch|request failed|not found|internal server|unexpected error|error loading|failed to load|could not load|unable to load|network error|status code 4\d\d|status code 5\d\d)\b/i;
function cleanText(x) { return String(x || '').replace(/\s+/g, ' ').trim().slice(0, 240); }
function safeName(x) { return x.replace(/[^a-z0-9._-]+/gi, '_').slice(0, 150) || 'page'; }
function sameOrigin(url) { try { return new URL(url).origin === new URL(BASE_URL).origin; } catch { return false; } }
function crawlable(url) {
  try {
    const u = new URL(url);
    if (!sameOrigin(u.href) || ignoredHref.test(u.href) || assetPath.test(u.pathname)) return false;
    if (u.pathname.startsWith('/api/') || u.pathname === '/authorize' || u.pathname.startsWith('/auth/callback')) return false;
    return `${u.origin}${u.pathname}${u.search}`;
  } catch { return false; }
}
function responseRecord(r) { return { status: r.status(), method: r.request().method(), url: r.url(), resourceType: r.request().resourceType() }; }
function relevant(events) { return events.filter(e => e.type !== 'response' || e.status >= 500 || !assetPath.test(e.url)); }
async function attach(page, events) {
  page.on('console', m => { if (['error', 'warning'].includes(m.type())) events.push({ type: 'console', level: m.type(), text: m.text(), url: page.url() }); });
  page.on('pageerror', e => events.push({ type: 'pageerror', text: String(e), url: page.url() }));
  page.on('response', r => { if (r.status() >= 400) events.push({ type: 'response', ...responseRecord(r) }); });
  page.on('requestfailed', r => events.push({ type: 'requestfailed', method: r.method(), url: r.url(), failure: r.failure()?.errorText || 'unknown' }));
}
async function settle(page, url) {
  let error = null;
  try { await page.goto(url, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT }); } catch (e) { error = String(e); }
  try { await page.waitForFunction(() => (document.body?.innerText || '').trim().length > 40, { timeout: 12000 }); } catch {}
  await page.waitForTimeout(SETTLE_MS);
  return error;
}
async function snapshot(page) {
  return page.evaluate(() => {
    const body = document.body?.innerText || '';
    const lines = body.split('\n').map(x => x.trim()).filter(Boolean);
    const visibleErrors = lines.filter(x => /\b(application error|something went wrong|failed to fetch|request failed|not found|internal server|unexpected error|error loading|failed to load|could not load|unable to load|network error|status code 4\d\d|status code 5\d\d)\b/i.test(x)).slice(0, 20);
    const links = [...document.querySelectorAll('a[href]')].map(a => ({ text: (a.innerText || a.getAttribute('aria-label') || a.title || '').replace(/\s+/g, ' ').trim().slice(0, 180), href: a.href, visible: (() => { const r=a.getBoundingClientRect(),s=getComputedStyle(a); return r.width>0&&r.height>0&&s.visibility!=='hidden'&&s.display!=='none'; })() }));
    const controls = [...document.querySelectorAll('button,[role="button"],summary,input[type="button"],input[type="submit"]')].map(x => { const r=x.getBoundingClientRect(),s=getComputedStyle(x); return { text:(x.innerText||x.getAttribute('aria-label')||x.title||x.value||'').replace(/\s+/g,' ').trim().slice(0,180), visible:r.width>0&&r.height>0&&s.visibility!=='hidden'&&s.display!=='none', disabled:x.disabled===true||x.getAttribute('aria-disabled')==='true' }; }).filter(x=>x.visible&&x.text);
    return { title: document.title, bodyText: body.slice(0, 3000), visibleTextLength: body.trim().length, visibleErrors, links, controls, skeletons: document.querySelectorAll('[aria-busy="true"],.animate-pulse,[data-state="loading"]').length };
  });
}
async function shot(page, label) { const file=path.join(OUTPUT,'screenshots',`${safeName(label)}.png`); try { await page.screenshot({path:file,fullPage:true,timeout:12000}); return file; } catch { return null; } }
async function worker(count, fn) { let next=0; async function run(){ while(true){ const i=next++; if(i>=count) return; await fn(i); } } await Promise.all(Array.from({length:Math.min(count,ROUTE_WORKERS*CLICK_WORKERS)},run)); }

(async()=>{
  const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_BROWSER||'/snap/bin/chromium',args:['--no-sandbox']});
  const context=await browser.newContext({httpCredentials:{username:process.env.E2E_USER,password:process.env.E2E_PASS},viewport:{width:1440,height:900},ignoreHTTPSErrors:false});
  const routes=new Set([START_URL]); const queue=[START_URL]; const routesOut=[];
  // Parallel route discovery with a scheduler that keeps workers alive while new links arrive.
  await new Promise(resolve => {
    let active = 0;
    const pump = () => {
      while (active < ROUTE_WORKERS && queue.length && routesOut.length < MAX_ROUTES) {
        const url = queue.shift(); active++;
        (async () => {
          const page=await context.newPage(); const events=[]; await attach(page,events); const navigationError=await settle(page,url); let snap;
          try { snap=await snapshot(page); } catch { snap={title:'',bodyText:'',visibleTextLength:0,visibleErrors:[],links:[],controls:[],skeletons:0}; }
          for(const link of snap.links){ const next=crawlable(link.href); if(next&&!routes.has(next)&&routes.size<MAX_ROUTES){routes.add(next);queue.push(next);} }
          const problems=[...relevant(events),...snap.visibleErrors.map(text=>({type:'visible-error',text,url}))];
          const result={url,finalUrl:page.url(),title:snap.title,navigationError,problems,skeletons:snap.skeletons,visibleTextLength:snap.visibleTextLength,linksDiscovered:snap.links.filter(x=>x.visible&&crawlable(x.href)).length,controls:snap.controls.map(x=>x.text)};
          if(navigationError||problems.length||!snap.visibleTextLength||snap.skeletons>0) result.screenshot=await shot(page,`route-${routesOut.length}-${url}`); else result.screenshot=null;
          routesOut.push(result); await page.close();
        })().catch(error => routesOut.push({url,finalUrl:url,title:'',navigationError:String(error),problems:[{type:'route-worker-error',text:String(error)}],skeletons:0,visibleTextLength:0,linksDiscovered:0,controls:[],screenshot:null})).finally(() => { active--; if ((queue.length===0 && active===0) || routesOut.length>=MAX_ROUTES && active===0) resolve(); else pump(); });
      }
      if (queue.length===0 && active===0) resolve();
    };
    pump();
  });
  routesOut.sort((a,b)=>a.url.localeCompare(b.url));

  const clickTasks=[];
  for(const route of routesOut){ const labels=[...new Set(route.controls)].slice(0,MAX_CONTROLS_PER_ROUTE); for(const text of labels){ const result={route:route.url,text,skipped:false,reason:null,finalUrl:null,problems:[],statusErrors:[],screenshot:null}; if(destructive.test(text)){result.skipped=true;result.reason='destructive-or-state-changing';} clickTasks.push(result); } }
  let clickNext=0;
  async function clickWorker(){
    while(true){ const i=clickNext++; if(i>=clickTasks.length)return; const result=clickTasks[i]; if(result.skipped)continue; const page=await context.newPage(); const events=[]; const dialogs=[]; page.on('dialog',async d=>{dialogs.push({type:d.type(),message:d.message()});await d.dismiss();}); await attach(page,events); const navErr=await settle(page,result.route);
      if(navErr) result.problems.push({type:'navigation-error',text:navErr}); else try {
        const found=await page.evaluate(wanted=>{const els=[...document.querySelectorAll('button,[role="button"],summary,input[type="button"],input[type="submit"]')]; const el=els.find(x=>{const r=x.getBoundingClientRect(),s=getComputedStyle(x),t=(x.innerText||x.getAttribute('aria-label')||x.title||x.value||'').replace(/\s+/g,' ').trim();return r.width>0&&r.height>0&&s.visibility!=='hidden'&&s.display!=='none'&&t===wanted&&!x.disabled&&x.getAttribute('aria-disabled')!=='true'});if(!el)return false;el.setAttribute('data-live-e2e-click-target','true');return true;},result.text);
        if(!found) result.problems.push({type:'click-target-missing',text:`${result.text}`}); else { await page.locator('[data-live-e2e-click-target="true"]').click({timeout:8000}); await page.waitForTimeout(850); }
      } catch(e){ result.problems.push({type:'click-error',text:String(e)}); }
      result.finalUrl=page.url(); result.statusErrors=relevant(events); let snap; try{snap=await snapshot(page);}catch{snap={visibleErrors:[],visibleTextLength:0};} result.problems.push(...snap.visibleErrors.map(text=>({type:'visible-error',text,url:page.url()})),...dialogs.map(d=>({type:'dialog',...d}))); if(result.problems.length||result.statusErrors.length||!snap.visibleTextLength)result.screenshot=await shot(page,`click-${i}-${result.text}`); await page.close();
    }
  }
  await Promise.all(Array.from({length:CLICK_WORKERS},clickWorker));

  const problems=[...routesOut.flatMap(r=>r.problems.map(p=>({scope:'route',route:r.url,...p}))),...clickTasks.flatMap(r=>r.problems.map(p=>({scope:'click',route:r.route,control:r.text,...p}))),...routesOut.flatMap(r=>r.problems.filter(p=>p.type==='response').map(p=>({scope:'route',route:r.url,...p}))),...clickTasks.flatMap(r=>r.statusErrors.map(p=>({scope:'click',route:r.route,control:r.text,...p})))];
  const report={generatedAt:new Date().toISOString(),baseUrl:BASE_URL,project:PROJECT,startUrl:START_URL,routeCount:routesOut.length,routeQueueExhausted:queue.length===0,routeLimitReached:routes.size>=MAX_ROUTES,controlTaskCount:clickTasks.length,clickedCount:clickTasks.filter(x=>!x.skipped).length,skippedCount:clickTasks.filter(x=>x.skipped).length,destructiveControlsSkipped:clickTasks.filter(x=>x.skipped).map(x=>({route:x.route,text:x.text})),routes:routesOut,clicks:clickTasks,problems,counts:{httpErrors:problems.filter(x=>x.type==='response').length,requestFailures:problems.filter(x=>x.type==='requestfailed').length,consoleErrorsWarnings:problems.filter(x=>x.type==='console').length,pageErrors:problems.filter(x=>x.type==='pageerror').length,visibleErrors:problems.filter(x=>x.type==='visible-error').length,clickErrors:problems.filter(x=>x.type==='click-error').length,navigationErrors:problems.filter(x=>x.type==='navigation-error').length}};
  fs.writeFileSync(path.join(OUTPUT,'report.json'),JSON.stringify(report,null,2));
  console.log(JSON.stringify({output:OUTPUT,routeCount:report.routeCount,routeQueueExhausted:report.routeQueueExhausted,routeLimitReached:report.routeLimitReached,controlTaskCount:report.controlTaskCount,clickedCount:report.clickedCount,skippedCount:report.skippedCount,counts:report.counts,firstProblems:problems.slice(0,40)},null,2));
  await browser.close(); process.exit(problems.length?1:0);
})().catch(e=>{console.error(e.stack||String(e));process.exit(2)});
