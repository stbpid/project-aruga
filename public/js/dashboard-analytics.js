// ── Shared Analytics + Regions JS ─────────────────────────────

const AN_SECTIONS = [
  { key: 'overview',     label: 'Overview Stats',           selector: '.stats-grid' },
  { key: 'trends',       label: 'Assessment Trends',        selector: '.grid-3-1' },
  { key: 'regions',      label: 'By Region',                headers: ['By Region'],               ids: ['an-region-bars','rgtab-province','rgtab-city','rg-table-count'] },
  { key: 'beneficiary',  label: 'Beneficiaries',            headers: ['Beneficiaries'],           ids: ['an-gender-donut','an-family-size-donut','an-age-bars','an-4ps-chart','an-religion-donut','an-top-locations'] },
  { key: 'disability',   label: 'Disabilities',             headers: ['Disabilities'],            ids: ['adm-dis-bars','an-multi-dis','an-dis-age'] },
  { key: 'housing',      label: 'Housing',                  headers: ['Housing'],                 ids: ['an-housing-materials','an-tenure-status','an-house-mod','an-water-supply'] },
  { key: 'interviewers', label: 'Interviewer Performance',  headers: ['Interviewer Performance'], ids: ['an-int-tbody','an-top5-int','an-workload-vis'] },
  { key: 'readiness',    label: 'Readiness Score Trends',   headers: ['Readiness Score Trends'],  ids: ['an-readiness-chart'] },
  { key: 'education',    label: 'Education',                headers: ['Education'],               ids: ['an-enrollment-donut','an-enrollment-reasons','an-educ-attainment'] },
  { key: 'economic',     label: 'Economic',                 headers: ['Economic'],                ids: ['an-income-chart','an-min-wage','an-expenses-chart'] },
  { key: 'services',     label: 'Services',                 headers: ['Services'],                ids: ['an-financial-assist-donut','an-service-gap-chart'] },
  { key: 'health',       label: 'Health',                   headers: ['Health'],                  ids: ['an-immunization-donut','an-health-issues-donut','an-health-availed-donut','an-vacc-rate','an-barriers-chart'] },
  { key: 'quality',      label: 'Data Quality',             headers: ['Data Quality'],            ids: ['an-gauge-pct','an-missing-fields','an-flagged-count'] },
  { key: 'reports',      label: 'Reports',                  headers: ['Reports'],                 ids: ['an-email-type'], locked: true },
];

let anSectionVisibility = {};
AN_SECTIONS.forEach(s => { anSectionVisibility[s.key] = true; });

let anTrendView = 'monthly';
let anAutoRefresh;
let anFilters = { range: 'all', region: '', disability: '' };
let AN_DATA = null;
let AN_CACHE_TS = 0;
const AN_CACHE_TTL = 5 * 60 * 1000;
const AN_EMPTY = '<div style="padding:1.5rem;text-align:center;color:var(--gray-400);font-size:0.75rem;">No data available</div>';
const AN_LOADING = '<div style="padding:1.5rem;text-align:center;color:var(--gray-400);font-size:0.75rem;">Loading...</div>';

const ADM_DIS_COLORS = ['#1152d4','#7c3aed','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#8b5cf6','#06b6d4','#84cc16'];

async function loadAnalyticsView() {
  await Promise.all([fetchAnalyticsData(), fetchExtendedAnalytics()]);
  renderAnAll();
  renderAnReadiness();
  renderAnEducation();
  renderAnEducationExtra();
  renderAnEconomic();
  renderAnEconomicExtra();
  renderAnServices();
  renderAnServicesExtra();
  renderAnHealth();
  renderAnBeneficiaryExtras();
  renderAnHousing();
  admLoadDisabilityDist();
  updateAnTimestamp();
  clearInterval(anAutoRefresh);
  anAutoRefresh = setInterval(async () => {
    AN_CACHE_TS = 0; AN_EXT = null; AN_EXT_CACHE_KEY = '';
    await Promise.all([fetchAnalyticsData(), fetchExtendedAnalytics()]);
    renderAnAll(); renderAnReadiness(); renderAnEducation(); renderAnEducationExtra();
    renderAnEconomic(); renderAnEconomicExtra(); renderAnServices(); renderAnServicesExtra();
    renderAnHealth(); renderAnBeneficiaryExtras(); renderAnHousing(); updateAnTimestamp();
  }, 300000);
}

async function fetchAnalyticsData(force = false) {
  if (!force && AN_DATA && (Date.now() - AN_CACHE_TS) < AN_CACHE_TTL) return;
  const params = new URLSearchParams({ range: anFilters.range });
  if (anFilters.region) params.set('region', anFilters.region);
  try {
    const json = await (await authFetch('/api/get-analytics.php?' + params)).json();
    if (json.success) { AN_DATA = json; AN_CACHE_TS = Date.now(); }
  } catch(e) { AN_DATA = AN_DATA || null; }
}

function renderAnAll() {
  if (!AN_DATA) return;
  renderAnOverview();
  renderAnTrend();
  renderAnRegionBars();
  renderAnBeneficiary();
  renderAnDisability();
  renderAnInterviewers();
  renderAnQuality();
  populateAnFilters();
}

function updateAnTimestamp() {
  const el = document.getElementById('an-last-refresh');
  if (el) el.textContent = 'Last refreshed: ' + new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) + ' · Auto-refreshes every 5 min';
}

function populateAnFilters() {
  if (!AN_DATA) return;
  const sel = document.getElementById('an-f-region');
  if (sel && sel.options.length <= 1 && AN_DATA.regions) {
    AN_DATA.regions.forEach(r => { const o = document.createElement('option'); o.value = r.region; o.textContent = r.region; sel.appendChild(o); });
  }
}

async function applyAnalyticsFilters() {
  anFilters.range      = document.getElementById('an-f-range').value;
  anFilters.region     = document.getElementById('an-f-region').value;
  anFilters.disability = '';
  AN_CACHE_TS = 0; AN_EXT = null; AN_EXT_CACHE_KEY = '';
  await Promise.all([fetchAnalyticsData(), fetchExtendedAnalytics()]);
  renderAnAll(); renderAnTrend(); renderAnReadiness();
  renderAnEducation(); renderAnEducationExtra();
  renderAnEconomic(); renderAnEconomicExtra();
  renderAnServices(); renderAnServicesExtra();
  renderAnHealth(); renderAnBeneficiaryExtras(); renderAnHousing();
  const parts = [];
  const rangeMap = {'30':'Last 30 days','7':'Last 7 days','90':'Last 90 days','all':'All time'};
  parts.push(rangeMap[anFilters.range] || anFilters.range);
  if (anFilters.region) parts.push(anFilters.region);
  applyRgFilters();
  if (typeof showGlobalToast === 'function') showGlobalToast('Filter applied: ' + parts.join(' · '), 'success');
}

async function clearAnalyticsFilters() {
  document.getElementById('an-f-range').value = 'all';
  document.getElementById('an-f-region').value = '';
  anFilters = { range:'all', region:'', disability:'' };
  await applyAnalyticsFilters();
}

function setAnTrendView(v) {
  anTrendView = v;
  ['daily','weekly','monthly','quarterly'].forEach(x => {
    const btn = document.getElementById('an-toggle-' + x);
    if (btn) btn.classList.toggle('active', x === v);
  });
  renderAnTrend();
}

const AN_CURRENT_YEAR  = new Date().getFullYear();
const AN_CURRENT_MONTH = new Date().getMonth() + 1;

async function renderAnTrend() {
  const container = document.getElementById('an-trend-bars');
  if (!container) return;
  container.innerHTML = AN_LOADING;
  const subtitleEl = document.getElementById('an-trend-sub');
  const subLabels = { daily:'Daily submissions — last 30 days', weekly:'Weekly submissions — last 8 weeks', monthly:'Monthly submissions — '+AN_CURRENT_YEAR };
  if (subtitleEl) subtitleEl.textContent = subLabels[anTrendView] || '';
  const monthly = AN_DATA?.trends?.monthly || [];
  container.innerHTML = '';
  if (!monthly.length) { container.innerHTML = AN_EMPTY; return; }
  const monthMap = {};
  monthly.forEach((d, i) => { monthMap[i + 1] = d.value; });
  let bars;
  if (anTrendView === 'monthly') {
    const maxVal = Math.max(...monthly.map(d => d.value), 1);
    bars = monthly.map((d, i) => {
      const month = i+1, isCurrent = month===AN_CURRENT_MONTH, isPrev = month===AN_CURRENT_MONTH-1||month===AN_CURRENT_MONTH-2, isFuture = month>AN_CURRENT_MONTH;
      return { label:d.label, value:d.value, maxVal, tooltip:d.label+' '+AN_CURRENT_YEAR+': '+d.value+' assessment'+(d.value!==1?'s':''), color:isCurrent?'#1152d4':isPrev?'#60a5fa':isFuture?'#e2e8f0':'#93c5fd', hoverColor:isCurrent?'#0d3fa3':isPrev?'#3b82f6':isFuture?'#cbd5e1':'#60a5fa', highlight:isCurrent };
    });
  } else if (anTrendView === 'quarterly') {
    const qs = [{label:'Q1',tip:'Q1 (Jan–Mar)',months:[1,2,3]},{label:'Q2',tip:'Q2 (Apr–Jun)',months:[4,5,6]},{label:'Q3',tip:'Q3 (Jul–Sep)',months:[7,8,9]},{label:'Q4',tip:'Q4 (Oct–Dec)',months:[10,11,12]}];
    const currentQ = Math.ceil(AN_CURRENT_MONTH/3);
    const rawBars = qs.map((q,i)=>{ const value=q.months.reduce((s,m)=>s+(monthMap[m]||0),0), isCurrentQ=(i+1)===currentQ, isPrevQ=(i+1)===currentQ-1, isFutureQ=(i+1)>currentQ; return {label:q.label,value,maxVal:0,tooltip:q.tip+': '+value+' assessment'+(value!==1?'s':''),color:isCurrentQ?'#1152d4':isPrevQ?'#60a5fa':isFutureQ?'#e2e8f0':'#93c5fd',hoverColor:isCurrentQ?'#0d3fa3':isPrevQ?'#3b82f6':isFutureQ?'#cbd5e1':'#60a5fa',highlight:isCurrentQ}; });
    const maxVal = Math.max(...rawBars.map(b=>b.value),1);
    bars = rawBars.map(b=>({...b,maxVal}));
  } else {
    const trendData = AN_DATA?.trends?.daily||[];
    if (!trendData.length) { container.innerHTML = AN_EMPTY; return; }
    const maxVal = Math.max(...trendData.map(d=>d.value),1);
    bars = trendData.map(d=>({label:d.label,value:d.value,maxVal,tooltip:d.label+': '+d.value+' assessment'+(d.value!==1?'s':''),color:'#93c5fd',hoverColor:'#1152d4',highlight:false}));
  }
  renderBarsInto(container, bars);
}

function renderBarsInto(container, bars) {
  container.innerHTML = '';
  const containerH = container.clientHeight || 160, usableH = containerH - 32;
  let tooltip = document.getElementById('chart-tooltip');
  if (!tooltip) { tooltip=document.createElement('div'); tooltip.id='chart-tooltip'; tooltip.style.cssText='position:fixed;background:#1f2937;color:white;font-size:0.7rem;font-weight:600;padding:0.3rem 0.625rem;border-radius:0.375rem;pointer-events:none;opacity:0;transition:opacity 0.15s;z-index:9000;white-space:nowrap;'; document.body.appendChild(tooltip); }
  bars.forEach(b => {
    const pct=b.value/b.maxVal, h=Math.max(pct*usableH,b.value>0?6:3);
    const col=document.createElement('div'); col.className='bar-col';
    const vd=document.createElement('div'); vd.style.cssText=`font-size:0.6rem;font-weight:700;color:${b.highlight?'#1152d4':'#9ca3af'};margin-bottom:3px;text-align:center;min-height:0.75rem;`; vd.textContent=b.value>0&&bars.length<=12?b.value:'';
    const bar=document.createElement('div'); bar.className='bar-fill'; bar.style.cssText=`height:${h}px;background:${b.color};border-radius:4px 4px 0 0;transition:background 0.15s,transform 0.15s;cursor:pointer;`;
    bar.addEventListener('mouseenter',function(){this.style.background=b.hoverColor;this.style.transform='scaleY(1.03)';this.style.transformOrigin='bottom';tooltip.textContent=b.tooltip;tooltip.style.opacity='1';});
    bar.addEventListener('mousemove',e=>{tooltip.style.left=(e.clientX+12)+'px';tooltip.style.top=(e.clientY-28)+'px';});
    bar.addEventListener('mouseleave',function(){this.style.background=b.color;this.style.transform='';tooltip.style.opacity='0';});
    const lbl=document.createElement('span'); lbl.className='bar-label'; lbl.style.cssText=`font-weight:${b.highlight?'700':'500'};color:${b.highlight?'#1152d4':'#6b7280'};`; lbl.textContent=b.label;
    col.appendChild(vd); col.appendChild(bar); col.appendChild(lbl); container.appendChild(col);
  });
}

function renderAnOverview() {
  const s = AN_DATA?.summary; if (!s) return;
  const setEl = (id, val) => { const el=document.getElementById(id); if(el) el.textContent=val; };
  setEl('an-total', (s.total||0).toLocaleString());
  setEl('an-total-sub', 'Across all regions');
  setEl('an-rate', (s.completion_rate||0)+'%');
  setEl('an-interviewers', (s.active_interviewers||0).toLocaleString());
  setEl('an-int-sub', 'Submitted at least once');
  setEl('an-avg-day', s.avg_per_day||0);
  const rate=s.completion_rate||0;
  const statusEl=document.getElementById('an-status-icon'), labelEl=document.getElementById('an-status-label'), subEl=document.getElementById('an-status-sub'), badge=document.getElementById('an-rate-badge');
  if (rate>=80) {
    if(statusEl){statusEl.style.background='#f0fdf4';statusEl.style.color='#16a34a';statusEl.querySelector('span').textContent='trending_up';}
    if(labelEl){labelEl.textContent='Ahead';labelEl.style.color='#16a34a';}
    if(subEl)subEl.textContent='Exceeding national target pace';
    if(badge)badge.innerHTML='<span class="badge badge-green">Ahead</span>';
  } else if (rate>=60) {
    if(statusEl){statusEl.style.background='#eff6ff';statusEl.style.color='#1152d4';statusEl.querySelector('span').textContent='check_circle';}
    if(labelEl){labelEl.textContent='On Track';labelEl.style.color='#1152d4';}
    if(subEl)subEl.textContent='Progressing within expected range';
    if(badge)badge.innerHTML='<span class="badge badge-blue">On Track</span>';
  } else {
    if(statusEl){statusEl.style.background='#fff1f1';statusEl.style.color='#ef4444';statusEl.querySelector('span').textContent='warning';}
    if(labelEl){labelEl.textContent='Behind';labelEl.style.color='#ef4444';}
    if(subEl)subEl.textContent='National pace below target — action needed';
    if(badge)badge.innerHTML='<span class="badge badge-red">Behind</span>';
  }
}

function renderAnRegionBars() {
  const container=document.getElementById('an-region-bars'); if(!container) return;
  const data=AN_DATA?.regions||[];
  if (!data.length) { container.innerHTML=AN_EMPTY; return; }
  const maxV=Math.max(...data.map(r=>r.count),1);
  container.innerHTML=data.map(r=>`<div title="${r.region}: ${r.count.toLocaleString()} assessments" style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="font-size:0.75rem;font-weight:600;color:var(--dark);width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${r.region}</div><div style="flex:1;height:10px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((r.count/maxV)*100)}%;background:var(--blue);border-radius:999px;transition:width 0.6s;"></div></div><div style="font-size:0.75rem;font-weight:800;color:var(--dark);width:48px;text-align:right;">${r.count.toLocaleString()}</div></div>`).join('');
}

function renderAnBeneficiary() {
  const ageC=document.getElementById('an-age-bars'); const ages=AN_DATA?.age_groups||[];
  if(ageC){ if(!ages.length){ageC.innerHTML=AN_EMPTY;}else{const maxA=Math.max(...ages.map(a=>a.val),1);ageC.innerHTML=ages.map(a=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="font-size:0.75rem;font-weight:600;width:60px;">${a.label}</div><div style="flex:1;height:10px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((a.val/maxA)*100)}%;background:#7c3aed;border-radius:999px;"></div></div><div style="font-size:0.75rem;font-weight:800;width:48px;text-align:right;">${a.val.toLocaleString()}</div></div>`).join('');}}
  const genderC=document.getElementById('an-gender-donut'); const gender=AN_DATA?.gender||[]; const gTotal=gender.reduce((s,g)=>s+g.val,0);
  if(genderC){ if(!gTotal){genderC.innerHTML=AN_EMPTY;}else{const circ=314;let offset=0;const arcs=gender.map(g=>{const pct=gTotal>0?g.val/gTotal:0;const dash=pct*circ;const arc=`<circle cx="60" cy="60" r="50" fill="none" stroke="${g.color}" stroke-width="20" stroke-dasharray="${dash} ${circ-dash}" stroke-dashoffset="${78.5-offset}" transform="rotate(-90 60 60)"/>`;offset+=dash;return arc;}).join('');genderC.innerHTML=`<svg viewBox="0 0 120 120" width="140" height="140"><circle cx="60" cy="60" r="50" fill="none" stroke="#e5e7eb" stroke-width="20"/>${arcs}</svg><div style="display:flex;gap:1.25rem;">${gender.map(g=>`<div style="text-align:center;"><div style="width:10px;height:10px;border-radius:50%;background:${g.color};margin:0 auto 4px;"></div><div style="font-size:0.75rem;font-weight:700;">${g.label}</div><div style="font-size:0.8125rem;font-weight:800;color:var(--dark);">${gTotal>0?Math.round((g.val/gTotal)*100):0}%</div></div>`).join('')}</div>`;}}
  const locC=document.getElementById('an-top-locations'); const locs=AN_DATA?.top_locations||[];
  if(locC){ if(!locs.length){locC.innerHTML=AN_EMPTY;}else{const maxL=Math.max(...locs.map(l=>l.count),1);locC.innerHTML=locs.map((l,i)=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><span style="font-size:0.8rem;font-weight:800;color:var(--gray-400);width:1.25rem;">${i+1}</span><div style="font-size:0.8125rem;font-weight:600;flex:1;">${l.name}</div><div style="width:120px;height:8px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((l.count/maxL)*100)}%;background:#10b981;border-radius:999px;"></div></div><div style="font-size:0.75rem;font-weight:800;width:36px;text-align:right;">${l.count}</div></div>`).join('');}}
}

function renderAnDisability() {
  const colors=['#1152d4','#7c3aed','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#8b5cf6','#06b6d4','#84cc16'];
  const disC=document.getElementById('an-dis-bars'); const dis=AN_DATA?.disabilities||[];
  if(disC){ if(!dis.length){disC.innerHTML=AN_EMPTY;}else{const maxD=Math.max(...dis.map(d=>d.val),1);disC.innerHTML=dis.map((d,i)=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="font-size:0.75rem;font-weight:600;width:110px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${d.label}</div><div style="flex:1;height:10px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((d.val/maxD)*100)}%;background:${colors[i%colors.length]};border-radius:999px;"></div></div><div style="font-size:0.75rem;font-weight:800;width:48px;text-align:right;">${d.val.toLocaleString()}</div></div>`).join('');}}
  const multiC=document.getElementById('an-multi-dis'); const multi=AN_DATA?.multi_dis||[];
  if(multiC){ if(!multi.length){multiC.innerHTML=AN_EMPTY;}else{multiC.innerHTML=multi.map(m=>`<div style="padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="display:flex;justify-content:space-between;margin-bottom:0.25rem;"><span style="font-size:0.8125rem;font-weight:600;">${m.label}</span><span style="font-size:0.8125rem;font-weight:800;color:var(--blue);">${m.pct}%</span></div><div style="height:10px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${m.pct}%;background:var(--blue);border-radius:999px;"></div></div></div>`).join('');}}
  const ageDisC=document.getElementById('an-dis-age'); const disAge=AN_DATA?.dis_age||[]; const disTypes=AN_DATA?.dis_types||[]; const dgColors=['#1152d4','#7c3aed','#ec4899','#f59e0b','#10b981'];
  if(ageDisC){ if(!disAge.length||!disTypes.length){ageDisC.innerHTML=AN_EMPTY;}else{ageDisC.innerHTML=`<div style="display:flex;gap:0.75rem;margin-bottom:0.5rem;flex-wrap:wrap;">${disTypes.map((t,i)=>`<div style="display:flex;align-items:center;gap:0.25rem;font-size:0.7rem;"><div style="width:10px;height:10px;border-radius:2px;background:${dgColors[i%dgColors.length]};"></div>${t}</div>`).join('')}</div>${disAge.map(ag=>{const tot=disTypes.reduce((s,t)=>s+(ag[t]||0),0);return`<div style="display:flex;align-items:center;gap:0.75rem;"><div style="font-size:0.75rem;font-weight:600;width:50px;">${ag.group}</div><div style="flex:1;height:14px;border-radius:3px;overflow:hidden;display:flex;">${disTypes.map((t,i)=>{const w=tot>0?Math.round(((ag[t]||0)/tot)*100):0;return w>0?`<div style="width:${w}%;background:${dgColors[i%dgColors.length]};height:100%;" title="${t}: ${ag[t]||0}"></div>`:''}).join('')}</div><div style="font-size:0.7rem;color:var(--gray-400);width:36px;">${tot}</div></div>`;}).join('')}`;}}
}

function renderAnInterviewers() {
  const WL={overloaded:'<span class="badge badge-red" style="font-size:0.65rem;">Overloaded</span>',balanced:'<span class="badge badge-green" style="font-size:0.65rem;">Balanced</span>',underutilized:'<span class="badge badge-orange" style="font-size:0.65rem;">Underutilized</span>'};
  const medals=['🥇','🥈','🥉','4.','5.'];
  const sorted=AN_DATA?.interviewers||[];
  const tbody=document.getElementById('an-int-tbody');
  if(tbody) tbody.innerHTML=sorted.length?sorted.map((iv,i)=>`<tr class="table-row-hover"><td style="font-weight:800;color:var(--gray-400);">${i+1}</td><td style="font-weight:700;">${iv.name}</td><td><span style="font-family:monospace;font-size:0.75rem;color:var(--blue);">${iv.code}</span></td><td style="font-size:0.75rem;">${iv.region}</td><td style="font-weight:800;color:#16a34a;">${iv.completed}</td><td>${iv.avgDay}</td><td>${WL[iv.workload]||''}</td></tr>`).join(''):'<tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:1.5rem;font-size:0.75rem;">No data available</td></tr>';
  const top5El=document.getElementById('an-top5-int');
  if(top5El) top5El.innerHTML=sorted.slice(0,5).map((iv,i)=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem;background:var(--gray-50);border-radius:0.5rem;border:1px solid var(--border);"><span style="font-size:1rem;min-width:1.5rem;">${medals[i]}</span><div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:0.8125rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${iv.name}</div><div style="font-size:0.7rem;color:var(--gray-400);">${(iv.region||'').split('–')[0].trim()}</div></div><span style="font-weight:800;color:#16a34a;">${iv.completed}</span></div>`).join('')||AN_EMPTY;
  const wlCounts={overloaded:0,balanced:0,underutilized:0};
  sorted.forEach(iv=>{if(wlCounts[iv.workload]!==undefined)wlCounts[iv.workload]++;});
  const wlTotal=sorted.length||1;
  const wlEl=document.getElementById('an-workload-vis');
  if(wlEl) wlEl.innerHTML=[{key:'overloaded',label:'Overloaded',color:'#ef4444'},{key:'balanced',label:'Balanced',color:'#16a34a'},{key:'underutilized',label:'Underutilized',color:'#f59e0b'}].map(w=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="font-size:0.75rem;font-weight:600;width:100px;">${w.label}</div><div style="flex:1;height:10px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((wlCounts[w.key]/wlTotal)*100)}%;background:${w.color};border-radius:999px;"></div></div><div style="font-size:0.75rem;font-weight:800;width:48px;text-align:right;">${wlCounts[w.key]} / ${wlTotal}</div></div>`).join('');
}

let AN_EXT = null, AN_EXT_CACHE_KEY = '';
async function fetchExtendedAnalytics() {
  const cacheKey=anFilters.range+'|'+anFilters.region;
  if (AN_EXT && AN_EXT_CACHE_KEY===cacheKey) return;
  const params=new URLSearchParams({range:anFilters.range});
  if (anFilters.region) params.set('region',anFilters.region);
  try { const json=await(await authFetch('/api/get-analytics-extended.php?'+params)).json(); if(json.success){AN_EXT=json;AN_EXT_CACHE_KEY=cacheKey;} } catch(e) {}
}

const READINESS_COLORS={severe:'#ef4444',moderate:'#f97316',low:'#eab308',stable:'#22c55e'};
const READINESS_KEYS=['severe','moderate','low','stable'];
const READINESS_LABELS={severe:'Severe',moderate:'Moderate',low:'Low',stable:'Stable'};

async function renderAnReadiness() {
  await fetchExtendedAnalytics();
  const container=document.getElementById('an-readiness-chart'); const legend=document.getElementById('an-readiness-legend');
  const data=AN_EXT?.readiness_trends||[];
  if(!container) return;
  if(!data.length){container.innerHTML=AN_EMPTY;return;}
  if(legend) legend.innerHTML=READINESS_KEYS.map(k=>`<div style="display:flex;align-items:center;gap:0.3rem;font-size:0.72rem;font-weight:600;"><div style="width:10px;height:10px;border-radius:2px;background:${READINESS_COLORS[k]};"></div>${READINESS_LABELS[k]}</div>`).join('');
  const maxVal=Math.max(...data.map(d=>READINESS_KEYS.reduce((s,k)=>s+(d[k]||0),0)),1);
  let tip=document.getElementById('chart-tooltip');
  if(!tip){tip=document.createElement('div');tip.id='chart-tooltip';tip.style.cssText='position:fixed;background:#1f2937;color:white;font-size:0.7rem;font-weight:600;padding:0.3rem 0.625rem;border-radius:0.375rem;pointer-events:none;opacity:0;transition:opacity 0.15s;z-index:9000;white-space:nowrap;';document.body.appendChild(tip);}
  container.style.display='flex'; container.style.alignItems='flex-end'; container.style.gap='0'; container.style.overflowX='auto';
  container.innerHTML=data.map(d=>{const total=READINESS_KEYS.reduce((s,k)=>s+(d[k]||0),0);const h=Math.max((total/maxVal)*160,total>0?4:3);const segments=READINESS_KEYS.filter(k=>d[k]>0).map(k=>{const segH=Math.round(((d[k]||0)/total)*h);return`<div style="height:${segH}px;background:${READINESS_COLORS[k]};width:100%;" title="${READINESS_LABELS[k]}: ${d[k]}"></div>`;}).join('');return`<div style="display:flex;flex-direction:column;align-items:center;flex:1;min-width:32px;cursor:default;" onmouseenter="document.getElementById('chart-tooltip').textContent='${d.label}: Total ${total}';document.getElementById('chart-tooltip').style.opacity='1';" onmousemove="var t=document.getElementById('chart-tooltip');t.style.left=(event.clientX+12)+'px';t.style.top=(event.clientY-28)+'px';" onmouseleave="document.getElementById('chart-tooltip').style.opacity='0';"><div style="font-size:0.55rem;font-weight:700;color:#9ca3af;margin-bottom:2px;">${total||''}</div><div style="display:flex;flex-direction:column-reverse;width:80%;border-radius:3px 3px 0 0;overflow:hidden;height:${h}px;">${segments}</div><div style="font-size:0.6rem;color:#6b7280;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;text-align:center;">${d.label.split(' ')[0]}</div></div>`;}).join('');
}

async function renderAnEducation() {
  await fetchExtendedAnalytics();
  const enr=AN_EXT?.enrollment;
  const donutEl=document.getElementById('an-enrollment-donut');
  if(!donutEl) return;
  if(!enr){donutEl.innerHTML=AN_EMPTY;return;}
  const total=(enr.enrolled||0)+(enr.not_enrolled||0), enrPct=total>0?Math.round((enr.enrolled/total)*100):0;
  const circ=2*Math.PI*50, dash=enrPct/100*circ;
  donutEl.innerHTML=`<svg viewBox="0 0 120 120" width="150" height="150" style="transform:rotate(-90deg);"><circle cx="60" cy="60" r="50" fill="none" stroke="#e5e7eb" stroke-width="20"/><circle cx="60" cy="60" r="50" fill="none" stroke="#22c55e" stroke-width="20" stroke-dasharray="${dash.toFixed(2)} ${(circ-dash).toFixed(2)}" stroke-dashoffset="0"/><circle cx="60" cy="60" r="50" fill="none" stroke="#ef4444" stroke-width="20" stroke-dasharray="${(circ-dash).toFixed(2)} ${dash.toFixed(2)}" stroke-dashoffset="${(-dash).toFixed(2)}"/></svg><div style="text-align:center;"><div style="font-size:1.5rem;font-weight:800;color:var(--dark);">${enrPct}%</div><div style="font-size:0.75rem;color:var(--gray-500);">Enrolled</div></div><div style="display:flex;gap:1rem;font-size:0.75rem;"><div style="display:flex;align-items:center;gap:0.3rem;"><div style="width:10px;height:10px;border-radius:50%;background:#22c55e;"></div>Enrolled: <b>${(enr.enrolled||0).toLocaleString()}</b></div><div style="display:flex;align-items:center;gap:0.3rem;"><div style="width:10px;height:10px;border-radius:50%;background:#ef4444;"></div>Not: <b>${(enr.not_enrolled||0).toLocaleString()}</b></div></div>`;
  const reasons=enr.top_reasons||[], maxR=Math.max(...reasons.map(r=>r.count),1);
  const reasonsEl=document.getElementById('an-enrollment-reasons');
  if(reasonsEl) reasonsEl.innerHTML=reasons.length?reasons.map(r=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="font-size:0.75rem;flex:1;font-weight:500;">${r.reason}</div><div style="width:100px;height:8px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((r.count/maxR)*100)}%;background:#f97316;border-radius:999px;"></div></div><div style="font-size:0.72rem;font-weight:800;width:28px;text-align:right;">${r.count}</div></div>`).join(''):AN_EMPTY;
}

async function renderAnEconomic() {
  await fetchExtendedAnalytics();
  const income=AN_EXT?.income||[], incColors=['#1152d4','#7c3aed','#ec4899','#f59e0b','#10b981','#ef4444'];
  const maxI=Math.max(...income.map(i=>i.count),1);
  const incEl=document.getElementById('an-income-chart');
  if(incEl) incEl.innerHTML=income.length?income.map((item,i)=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="font-size:0.75rem;font-weight:600;width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.label}</div><div style="flex:1;height:10px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((item.count/maxI)*100)}%;background:${incColors[i%incColors.length]};border-radius:999px;"></div></div><div style="font-size:0.72rem;font-weight:800;width:36px;text-align:right;">${item.count}</div><div style="font-size:0.7rem;color:var(--gray-400);width:36px;">${item.pct}%</div></div>`).join(''):AN_EMPTY;
  const expData=AN_EXT?.expenses, expColors=['#1152d4','#7c3aed','#ec4899','#f59e0b','#10b981'];
  const expChartEl=document.getElementById('an-expenses-chart'), expTotalEl=document.getElementById('an-expenses-total');
  if(expChartEl){ if(!expData){expChartEl.innerHTML=AN_EMPTY;return;} if(expTotalEl)expTotalEl.textContent='Average total monthly health expenses: ₱'+(expData.total_avg||0).toLocaleString(); const maxE=Math.max(...expData.breakdown.map(e=>e.avg),1); expChartEl.innerHTML=expData.breakdown.length?expData.breakdown.map((e,i)=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="font-size:0.75rem;font-weight:600;width:130px;">${e.label}</div><div style="flex:1;height:10px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((e.avg/maxE)*100)}%;background:${expColors[i%expColors.length]};border-radius:999px;"></div></div><div style="font-size:0.72rem;font-weight:800;width:64px;text-align:right;">₱${e.avg.toLocaleString()}</div></div>`).join(''):AN_EMPTY;}
}

async function renderAnServices() {
  await fetchExtendedAnalytics();
  const gap=AN_EXT?.service_gap||[];
  const container=document.getElementById('an-service-gap-chart');
  if(!container) return;
  if(!gap.length){container.innerHTML=AN_EMPTY;return;}
  container.innerHTML=gap.map(r=>`<div style="display:flex;align-items:center;gap:0.75rem;"><div style="font-size:0.72rem;font-weight:600;width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${r.region}</div><div style="flex:1;display:flex;flex-direction:column;gap:3px;"><div style="display:flex;align-items:center;gap:0.5rem;"><div style="font-size:0.65rem;color:var(--gray-400);width:42px;">Aware</div><div style="flex:1;height:8px;background:var(--gray-100);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${r.aware_pct}%;background:#1152d4;border-radius:999px;"></div></div><div style="font-size:0.7rem;font-weight:800;width:32px;text-align:right;">${r.aware_pct}%</div></div><div style="display:flex;align-items:center;gap:0.5rem;"><div style="font-size:0.65rem;color:var(--gray-400);width:42px;">Availed</div><div style="flex:1;height:8px;background:var(--gray-100);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${r.availed_pct}%;background:#22c55e;border-radius:999px;"></div></div><div style="font-size:0.7rem;font-weight:800;width:32px;text-align:right;">${r.availed_pct}%</div></div></div><div style="font-size:0.65rem;color:#ef4444;font-weight:700;width:44px;text-align:right;">-${r.aware_pct-r.availed_pct}%</div></div>`).join('');
}

async function renderAnHealth() {
  await fetchExtendedAnalytics();
  const vacc=AN_EXT?.vaccination, barriers=AN_EXT?.barriers||[];
  if(vacc){
    const vaccRateEl=document.getElementById('an-vacc-rate'); if(vaccRateEl)vaccRateEl.textContent=(vacc.national_rate||0)+'%';
    const byRegion=vacc.by_region||[], maxV=Math.max(...byRegion.map(r=>r.rate),1);
    const vaccBarsEl=document.getElementById('an-vacc-bars');
    if(vaccBarsEl) vaccBarsEl.innerHTML=byRegion.length?byRegion.map(r=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="font-size:0.72rem;font-weight:600;width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${r.region}</div><div style="flex:1;height:8px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${r.rate}%;background:${r.rate>=80?'#22c55e':r.rate>=50?'#1152d4':'#ef4444'};border-radius:999px;"></div></div><div style="font-size:0.72rem;font-weight:800;width:36px;text-align:right;">${r.rate}%</div></div>`).join(''):AN_EMPTY;
  }
  const maxB=Math.max(...barriers.map(b=>b.count),1);
  const barriersEl=document.getElementById('an-barriers-chart');
  if(barriersEl) barriersEl.innerHTML=barriers.length?barriers.map(b=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background=''"><div style="font-size:0.75rem;font-weight:500;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${b.label}</div><div style="width:120px;height:8px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((b.count/maxB)*100)}%;background:#f97316;border-radius:999px;"></div></div><div style="font-size:0.72rem;font-weight:800;width:28px;text-align:right;">${b.count}</div></div>`).join(''):AN_EMPTY;
}

function renderAnQuality() {
  const q=AN_DATA?.quality; if(!q) return;
  const score=q.completeness||0;
  const gaugeEl=document.getElementById('an-gauge-pct'); if(gaugeEl)gaugeEl.textContent=score+'%';
  const gaugeLabel=document.getElementById('an-gauge-label'); if(gaugeLabel)gaugeLabel.textContent=score>=90?'Excellent data quality':score>=75?'Good — minor gaps':'Needs improvement';
  const circle=document.getElementById('an-gauge-circle');
  if(circle){setTimeout(()=>{circle.style.strokeDashoffset=314-(score/100)*314;circle.style.stroke=score>=90?'#16a34a':score>=75?'#1152d4':'#ef4444';},200);}
  const missing=q.missing_fields||[], maxM=Math.max(...missing.map(f=>f.pct),1);
  const missingEl=document.getElementById('an-missing-fields');
  if(missingEl) missingEl.innerHTML=missing.length?missing.map(f=>`<div style="display:flex;align-items:center;gap:0.75rem;"><div style="font-size:0.75rem;flex:1;">${f.field}</div><div style="width:80px;height:8px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((f.pct/maxM)*100)}%;background:#f59e0b;border-radius:999px;"></div></div><div style="font-size:0.7rem;font-weight:800;width:32px;text-align:right;color:#d97706;">${f.pct}%</div></div>`).join(''):AN_EMPTY;
  const flaggedEl=document.getElementById('an-flagged-count'); if(flaggedEl)flaggedEl.textContent=q.flagged_count||0;
}

let admAllDisabilities = [];
async function admLoadDisabilityDist() {
  try {
    const json=await(await authFetch('/api/get-regions-stats.php')).json();
    admAllDisabilities=(json.success&&json.disabilities)?json.disabilities:[];
    if(json.success&&json.regions){
      const sel=document.getElementById('adm-dis-region-filter');
      if(sel){const regions=[...new Set(json.regions.map(r=>r.region))].sort();regions.forEach(r=>{const o=document.createElement('option');o.value=r;o.textContent=r;sel.appendChild(o);});}
    }
    admRenderDisBars();
  } catch(e){}
}

async function admRenderDisBars() {
  const region=document.getElementById('adm-dis-region-filter')?.value||'';
  let dis=admAllDisabilities;
  if(region){try{const res=await(await authFetch('/api/get-regions-stats.php?region='+encodeURIComponent(region))).json();dis=(res.success&&res.disabilities)?res.disabilities:[];}catch(e){dis=[];}}
  const container=document.getElementById('adm-dis-bars'), summary=document.getElementById('adm-dis-summary');
  if(!container) return;
  if(!dis.length){container.innerHTML='<div style="color:var(--gray-400);font-size:0.75rem;padding:1rem;text-align:center;">No disability data.</div>';if(summary)summary.innerHTML='';return;}
  const maxD=Math.max(...dis.map(d=>d.count),1), total=dis.reduce((s,d)=>s+d.count,0);
  container.innerHTML=dis.map((d,i)=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.25rem 0.375rem;border-radius:0.375rem;transition:background 0.15s;" onmouseover="this.style.background='var(--blue-light)'" onmouseout="this.style.background='transparent'"><div style="font-size:0.75rem;font-weight:600;width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${d.label}</div><div style="flex:1;height:10px;background:var(--gray-100);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((d.count/maxD)*100)}%;background:${ADM_DIS_COLORS[i%ADM_DIS_COLORS.length]};border-radius:999px;transition:width 0.5s;"></div></div><div style="font-size:0.75rem;font-weight:800;width:40px;text-align:right;">${d.count}</div><div style="font-size:0.7rem;color:var(--gray-400);width:36px;text-align:right;">${Math.round((d.count/total)*100)}%</div></div>`).join('');
  if(summary) summary.innerHTML=`<div style="padding:0.75rem;background:var(--gray-50);border-radius:0.625rem;border:1px solid var(--border);margin-bottom:0.5rem;"><div style="font-size:0.7rem;color:var(--gray-400);">Total Disability Records</div><div style="font-size:1.5rem;font-weight:800;color:var(--dark);">${total.toLocaleString()}</div></div><div style="font-size:0.75rem;font-weight:600;color:var(--gray-700);margin-bottom:0.375rem;">Top 3</div>${dis.slice(0,3).map((d,i)=>`<div style="display:flex;align-items:center;justify-content:space-between;padding:0.375rem 0;border-bottom:1px solid var(--gray-100);"><div style="display:flex;align-items:center;gap:0.5rem;"><div style="width:10px;height:10px;border-radius:50%;background:${ADM_DIS_COLORS[i]};flex-shrink:0;"></div><span style="font-size:0.8rem;font-weight:600;">${d.label}</span></div><span style="font-size:0.8rem;font-weight:800;color:var(--dark);">${d.count} <span style="color:var(--gray-400);font-weight:400;">(${Math.round((d.count/total)*100)}%)</span></span></div>`).join('')}`;
}

function downloadReport(type, format) {
  const d=AN_DATA;
  let headers, rows;
  if(type==='executive'){headers=['Metric','Value'];rows=[['Total Assessments',d?.summary?.total||0],['Completion Rate',(d?.summary?.completion_rate||0)+'%'],['Active Interviewers',d?.summary?.active_interviewers||0],['Avg/Day',d?.summary?.avg_per_day||0],['Flagged Records',d?.quality?.flagged_count||0],['Data Completeness',(d?.quality?.completeness||0)+'%']];}
  else if(type==='regional'){headers=['Region','Completed'];rows=(d?.regions||[]).map(r=>[r.region,r.count]);}
  else if(type==='disability'){headers=['Disability Type','Count'];rows=(d?.disabilities||[]).map(x=>[x.label,x.val]);}
  else{headers=['Rank','Name','Code','Region','Completed','Avg/Day','Workload'];rows=(d?.interviewers||[]).map((iv,i)=>[i+1,iv.name,iv.code,iv.region,iv.completed,iv.avgDay,iv.workload]);}
  if(format==='pdf'){
    const titles={executive:'Executive Summary',regional:'Regional Breakdown',disability:'Disability Report',interviewer:'Interviewer Performance'};
    const date=new Date().toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'});
    const tableRows=rows.map(row=>`<tr>${row.map(v=>`<td>${v}</td>`).join('')}</tr>`).join('');

    // Build section-specific insights
    let insightSections='';
    if(type==='executive'){
      const total=d?.summary?.total||0;
      const rate=d?.summary?.completion_rate||0;
      const flagged=d?.quality?.flagged_count||0;
      const completeness=d?.quality?.completeness||0;
      const avgDay=d?.summary?.avg_per_day||0;
      const activeInt=d?.summary?.active_interviewers||0;

      const rateStatus=rate>=80?'strong':'moderate';
      const completenessStatus=completeness>=90?'high':'needs attention';

      insightSections=`
        <div class="insight-block">
          <div class="insight-title">Key Insights</div>
          <ul class="insight-list">
            <li>A total of <strong>${total.toLocaleString()}</strong> assessments have been recorded, with a completion rate of <strong>${rate}%</strong> — indicating <strong>${rateStatus}</strong> field progress.</li>
            <li><strong>${activeInt}</strong> active interviewer(s) are contributing an average of <strong>${avgDay}</strong> assessments per day, reflecting current field capacity.</li>
            <li>Data completeness stands at <strong>${completeness}%</strong> (${completenessStatus}), with <strong>${flagged}</strong> flagged record(s) requiring review or correction.</li>
            ${completeness<90?'<li>Data quality improvement is recommended — incomplete or inconsistent records may affect program targeting accuracy.</li>':''}
            ${rate<50?'<li>Completion rate is below 50%. Consider deploying additional field personnel or reallocating workload across regions.</li>':''}
          </ul>
        </div>`;
    } else if(type==='regional'){
      const regions=d?.regions||[];
      const top=regions.slice().sort((a,b)=>b.count-a.count)[0];
      const low=regions.slice().sort((a,b)=>a.count-b.count)[0];
      insightSections=`
        <div class="insight-block">
          <div class="insight-title">Regional Insights</div>
          <ul class="insight-list">
            ${top?`<li><strong>${top.region}</strong> leads with the highest number of completed assessments (${top.count.toLocaleString()}), indicating strong field activity in this area.</li>`:''}
            ${low&&low.region!==top?.region?`<li><strong>${low.region}</strong> has the lowest completions (${low.count.toLocaleString()}). Targeted intervention or resource reallocation may be needed.</li>`:''}
            <li>Regional data helps identify geographic disparities in program reach and coverage across the country.</li>
          </ul>
        </div>`;
    } else if(type==='disability'){
      const dis=d?.disabilities||[];
      const top3=dis.slice().sort((a,b)=>b.val-a.val).slice(0,3);
      insightSections=`
        <div class="insight-block">
          <div class="insight-title">Disability Profile Insights</div>
          <ul class="insight-list">
            ${top3.map(x=>`<li><strong>${x.label}</strong> is among the most prevalent disability types recorded (${x.val.toLocaleString()} cases).</li>`).join('')}
            <li>Understanding disability distribution is essential for tailoring DSWD services and support interventions for PWDs.</li>
          </ul>
        </div>`;
    } else {
      const interviewers=d?.interviewers||[];
      const top=interviewers[0];
      insightSections=`
        <div class="insight-block">
          <div class="insight-title">Interviewer Performance Insights</div>
          <ul class="insight-list">
            ${top?`<li><strong>${top.name}</strong> leads in performance with <strong>${top.completed}</strong> completed assessments and an average of <strong>${top.avgDay}</strong>/day.</li>`:''}
            <li>Monitoring interviewer workload helps ensure equitable distribution of field tasks and early detection of burnout or underperformance.</li>
            <li>Field officers with consistently high output may be considered for mentorship or supervisory roles.</li>
          </ul>
        </div>`;
    }

    const html=`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${titles[type]}</title><style>
      @page{margin:0.5in;}
      *{box-sizing:border-box;}
      body{font-family:Arial,sans-serif;color:#111;margin:0;padding:0;}
      .page-header{position:fixed;top:0;left:0;right:0;padding:0.5in 0.5in 0.15in 0.5in;background:#fff;}
      .header-inner{display:flex;align-items:center;gap:1rem;}
      .header-logo{display:flex;align-items:center;gap:0.75rem;flex-shrink:0;}
      .header-logo img{height:52px;width:auto;}
      .header-org{text-align:center;flex:1;}
      .header-org .org-name{font-size:1rem;font-weight:900;letter-spacing:0.04em;color:#1152d4;text-transform:uppercase;}
      .header-org .org-sub{font-size:0.72rem;color:#374151;font-weight:600;text-transform:uppercase;letter-spacing:0.03em;}
      .header-divider{border:none;border-top:2px solid #000;margin:0.15in 0 0 0;}
      .page-footer{position:fixed;bottom:0;left:0;right:0;padding:0.1in 0.5in 0.5in 0.5in;background:#fff;}
      .footer-divider{border:none;border-top:1px solid #d1d5db;margin-bottom:0.1in;}
      .footer-text{font-size:0.7rem;color:#6b7280;display:flex;justify-content:space-between;}
      .content{margin-top:1.5in;margin-bottom:1.2in;padding:0 0.5in;}
      .report-title{font-size:1.3rem;font-weight:900;margin:0 0 0.1rem;color:#111;}
      .report-sub{font-size:0.8rem;color:#6b7280;margin-bottom:1.25rem;}
      table{width:100%;border-collapse:collapse;font-size:0.85rem;page-break-inside:avoid;}
      thead{display:table-header-group;}
      th{background:#1152d4;color:#fff;padding:0.5rem 0.75rem;text-align:left;}
      td{padding:0.45rem 0.75rem;border-bottom:1px solid #e5e7eb;}
      tr:nth-child(even) td{background:#f9fafb;}
      .insight-block{margin-top:1.5rem;padding:0.85rem 1rem;background:#f0f4ff;border-left:4px solid #1152d4;border-radius:0 0.375rem 0.375rem 0;page-break-inside:avoid;}
      .insight-title{font-size:0.8rem;font-weight:800;color:#1152d4;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;}
      .insight-list{margin:0;padding-left:1.1rem;font-size:0.8rem;color:#374151;line-height:1.7;}
      .insight-list li{margin-bottom:0.2rem;}
    </style></head><body>
      <div class="page-header">
        <div class="header-inner">
          <div class="header-logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/6/6f/DSWD_seal.svg/240px-DSWD_seal.svg.png" alt="DSWD" onerror="this.style.display='none'"/>
            <div>
              <div style="font-size:1.1rem;font-weight:900;color:#1152d4;letter-spacing:0.03em;">DSWD</div>
              <div style="font-size:0.6rem;color:#374151;font-weight:600;max-width:110px;line-height:1.3;">Department of Social Welfare and Development</div>
            </div>
          </div>
          <div class="header-org">
            <div class="org-name">Social Technology Bureau</div>
            <div class="org-sub">Innovations and Program Development Group</div>
          </div>
        </div>
        <hr class="header-divider"/>
      </div>
      <div class="page-footer">
        <hr class="footer-divider"/>
        <div class="footer-text">
          <span>Project Aruga &mdash; DSWD Child Assessment Profiling System</span>
          <span>Generated: ${date}</span>
        </div>
      </div>
      <div class="content">
        <div class="report-title">${titles[type]}</div>
        <div class="report-sub">Project Aruga &mdash; Generated ${date}</div>
        <table>
          <thead><tr>${headers.map(h=>`<th>${h}</th>`).join('')}</tr></thead>
          <tbody>${tableRows}</tbody>
        </table>
        ${insightSections}
      </div>
    </body></html>`;
    const w=window.open('','_blank'); w.document.write(html); w.document.close(); w.focus(); setTimeout(()=>{w.print();},400); return;
  }
  const csv=[headers,...rows].map(row=>row.map(v=>'"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n');
  const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download=`${type}-report-${new Date().toISOString().slice(0,10)}.csv`; a.click();
}

function emailReport() {
  const email=document.getElementById('an-email-addr')?.value.trim();
  const type=document.getElementById('an-email-type')?.value;
  if(!email||!email.includes('@')){if(typeof showGlobalToast==='function')showGlobalToast('Enter a valid email address.','error');return;}
  document.getElementById('an-email-addr').value='';
  if(typeof showGlobalToast==='function') showGlobalToast(`${type.charAt(0).toUpperCase()+type.slice(1)} report queued for delivery to ${email}.`,'success');
}

function exportAllAnalytics() {
  const d=AN_DATA;
  const sections=[{title:'Overview',headers:['Metric','Value'],rows:[['Total',d?.summary?.total||0],['Rate',(d?.summary?.completion_rate||0)+'%'],['Interviewers',d?.summary?.active_interviewers||0],['Avg/Day',d?.summary?.avg_per_day||0]]},{title:'Regions',headers:['Region','Count'],rows:(d?.regions||[]).map(r=>[r.region,r.count])},{title:'Disabilities',headers:['Type','Count'],rows:(d?.disabilities||[]).map(x=>[x.label,x.val])}];
  const csv=sections.map(s=>'# '+s.title+'\n'+[s.headers,...s.rows].map(r=>r.map(v=>'"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n')).join('\n\n');
  const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download=`analytics-full-${new Date().toISOString().slice(0,10)}.csv`; a.click();
}

// ── Regions View ──────────────────────────────────────────────

let RG_DATA={summary:{},regions:[],provinces:[],cities:[],disabilities:[],monthly_trend:[]};
let rgLoaded=false;
let rgSortState={regions:{key:'total',dir:-1},provinces:{key:'total',dir:-1},cities:{key:'total',dir:-1}};
let rgCurrentTab='overview';

const RG_TARGETS={'Region I (Ilocos Region)':150,'Region II (Cagayan Valley)':100,'Region III (Central Luzon)':100,'Region IV-A (CALABARZON)':100,'Region IV-B (MIMAROPA)':150,'Region V (Bicol Region)':100,'Region VI (Western Visayas)':150,'Region XI (Davao)':150,'National Capital Region':140};

async function loadRegionsView() {
  if(!rgLoaded){
    ['rg-region-tbody','rg-province-tbody','rg-city-tbody'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML='<tr><td colspan="5" style="text-align:center;color:var(--gray-400);padding:2rem;font-size:0.75rem;">Loading...</td></tr>';});
    try{const json=await(await authFetch('/api/get-regions-stats.php')).json();if(json.success){RG_DATA.summary=json.summary||{};RG_DATA.regions=json.regions||[];RG_DATA.provinces=json.provinces||[];RG_DATA.cities=json.cities||[];RG_DATA.disabilities=json.disabilities||[];RG_DATA.monthly_trend=json.monthly_trend||[];}}catch(e){}
    rgLoaded=true;
  }
  applyRgFilters();
  showRgTab('overview');
}

function applyRgFilters() {
  const region=(document.getElementById('an-f-region')||{}).value||'';
  const filteredRegions=RG_DATA.regions.filter(r=>!region||r.region===region);
  const filteredProvinces=RG_DATA.provinces.filter(r=>!region||r.region===region);
  const filteredCities=RG_DATA.cities.filter(r=>!region||r.region===region);
  const countEl=document.getElementById('rg-table-count');
  if(countEl)countEl.textContent=filteredRegions.length+' region'+(filteredRegions.length!==1?'s':'');
  renderRgRegionTable(filteredRegions);
  renderRgProvinceTable(filteredProvinces);
  renderRgCityTable(filteredCities);
  renderRgInsights();
}

function resetRegionFilters(){applyRgFilters();}

function sortRg(dataset,key){const state=rgSortState[dataset];if(state.key===key)state.dir*=-1;else{state.key=key;state.dir=-1;}applyRgFilters();}

function sortedData(arr,dataset){const{key,dir}=rgSortState[dataset];return[...arr].sort((a,b)=>{const av=a[key]??'',bv=b[key]??'';return typeof av==='string'?av.localeCompare(bv)*dir:(av-bv)*dir;});}

function rateBar(rate){const c=rate>=70?'#16a34a':rate>=45?'#f59e0b':'#ef4444';return`<div style="display:flex;align-items:center;gap:0.5rem;"><div style="flex:1;height:6px;background:var(--gray-200);border-radius:999px;overflow:hidden;min-width:60px;"><div style="height:100%;width:${rate}%;background:${c};border-radius:999px;"></div></div><span style="font-weight:700;font-size:0.8125rem;color:${c};min-width:36px;">${rate}%</span></div>`;}

function renderRgRegionTable(data){
  const tbody=document.getElementById('rg-region-tbody'); if(!tbody) return;
  const withData=data.filter(r=>r.total>0), sorted=sortedData(withData,'regions');
  tbody.innerHTML=sorted.length?sorted.map(r=>{const target=RG_TARGETS[r.region]||null,pct=target?Math.min(Math.round((r.total/target)*100),100):r.rate,pctColor=pct>=100?'#16a34a':pct>=70?'#1152d4':pct>=45?'#f59e0b':'#ef4444';const targetCell=target?`<td style="font-weight:700;color:var(--gray-700);">${target.toLocaleString()}</td>`:`<td style="color:var(--gray-400);font-size:0.75rem;">—</td>`;const rateCell=target?`<td><div style="display:flex;align-items:center;gap:0.5rem;"><div style="flex:1;height:6px;background:var(--gray-200);border-radius:999px;overflow:hidden;min-width:60px;"><div style="height:100%;width:${pct}%;background:${pctColor};border-radius:999px;"></div></div><span style="font-weight:700;font-size:0.8125rem;color:${pctColor};min-width:40px;">${pct}%</span></div></td>`:`<td>${rateBar(r.rate)}</td>`;return`<tr class="table-row-hover"><td><div style="font-weight:700;color:var(--dark);">${r.region}</div></td><td style="font-weight:700;">${r.total.toLocaleString()}</td>${targetCell}${rateCell}<td>${r.interviewers}</td><td>${r.province_count}</td></tr>`;}).join(''):'<tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:2rem;font-size:0.75rem;">No data.</td></tr>';
}

function renderRgProvinceTable(data){
  const tbody=document.getElementById('rg-province-tbody'); if(!tbody) return;
  const sorted=sortedData(data,'provinces');
  tbody.innerHTML=sorted.length?(()=>{const maxT=Math.max(...sorted.map(r=>r.total),1);return sorted.map(r=>`<tr class="table-row-hover"><td style="font-size:0.75rem;color:var(--gray-500);">${r.region}</td><td style="font-weight:700;color:var(--dark);">${r.province||'—'}</td><td><div style="display:flex;align-items:center;gap:0.625rem;"><div style="flex:1;height:8px;background:var(--gray-100);border-radius:999px;overflow:hidden;min-width:80px;"><div style="height:100%;width:${Math.round((r.completed/maxT)*100)}%;background:#16a34a;border-radius:999px;transition:width 0.4s;"></div></div><span style="font-size:0.7rem;color:#16a34a;font-weight:700;min-width:36px;">${r.total.toLocaleString()}</span></div></td></tr>`).join('');})():'<tr><td colspan="3" style="text-align:center;color:var(--gray-400);padding:2rem;font-size:0.75rem;">No data.</td></tr>';
}

function renderRgCityTable(data){
  const tbody=document.getElementById('rg-city-tbody'); if(!tbody) return;
  const sorted=sortedData(data,'cities');
  tbody.innerHTML=sorted.length?(()=>{const maxT=Math.max(...sorted.map(r=>r.total),1);return sorted.map(r=>`<tr class="table-row-hover"><td style="font-size:0.75rem;color:var(--gray-500);">${r.region}</td><td style="font-size:0.75rem;color:var(--gray-500);">${r.province||'—'}</td><td style="font-weight:700;color:var(--dark);">${r.city||'—'}</td><td><div style="display:flex;align-items:center;gap:0.625rem;"><div style="flex:1;height:8px;background:var(--gray-100);border-radius:999px;overflow:hidden;min-width:80px;"><div style="height:100%;width:${Math.round((r.completed/maxT)*100)}%;background:#16a34a;border-radius:999px;transition:width 0.4s;"></div></div><span style="font-size:0.7rem;color:#16a34a;font-weight:700;min-width:36px;">${r.total.toLocaleString()}</span></div></td></tr>`).join('');})():'<tr><td colspan="4" style="text-align:center;color:var(--gray-400);padding:2rem;font-size:0.75rem;">No data.</td></tr>';
}

function renderRgInsights(){
  const top5=[...RG_DATA.regions].sort((a,b)=>b.total-a.total).slice(0,5);
  const top5El=document.getElementById('rg-top5');
  if(top5El)top5El.innerHTML=top5.length?top5.map(r=>`<div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem;background:var(--gray-50);border-radius:0.5rem;border:1px solid var(--border);"><div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:0.8125rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${r.region}</div><div style="font-size:0.7rem;color:var(--gray-400);">${r.completed.toLocaleString()} completed of ${r.total.toLocaleString()}</div></div><span style="font-weight:800;color:#1152d4;">${r.total.toLocaleString()}</span></div>`).join(''):'<div style="color:var(--gray-400);font-size:0.75rem;padding:1rem;">No data.</div>';
  const dis=RG_DATA.disabilities, maxD=dis.length?Math.max(...dis.map(d=>d.count),1):1;
  const disBarsEl=document.getElementById('rg-dis-bars');
  if(disBarsEl)disBarsEl.innerHTML=dis.length?dis.map(d=>`<div style="display:flex;align-items:center;gap:0.75rem;"><div style="font-size:0.75rem;font-weight:600;width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${d.label}</div><div style="flex:1;height:10px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:${Math.round((d.count/maxD)*100)}%;background:#7c3aed;border-radius:999px;"></div></div><div style="font-size:0.75rem;font-weight:800;width:40px;text-align:right;">${d.count}</div></div>`).join(''):'<div style="color:var(--gray-400);font-size:0.75rem;padding:1rem;">No disability data.</div>';
  const trend=RG_DATA.monthly_trend, container=document.getElementById('rg-monthly-bars');
  if(container&&trend.length){container.innerHTML='';const maxT=Math.max(...trend.map(t=>t.count),1),usableH=(container.clientHeight||160)-32;let tip=document.getElementById('chart-tooltip');if(!tip){tip=document.createElement('div');tip.id='chart-tooltip';tip.style.cssText='position:fixed;background:#1f2937;color:white;font-size:0.7rem;font-weight:600;padding:0.3rem 0.625rem;border-radius:0.375rem;pointer-events:none;opacity:0;transition:opacity 0.15s;z-index:9000;white-space:nowrap;';document.body.appendChild(tip);}trend.forEach(t=>{const h=Math.max(Math.round((t.count/maxT)*usableH),4);const col=document.createElement('div');col.className='bar-col';const vd=document.createElement('div');vd.style.cssText='font-size:0.55rem;font-weight:700;color:#9ca3af;margin-bottom:3px;text-align:center;min-height:0.75rem;';vd.textContent=t.count;const bar=document.createElement('div');bar.className='bar-fill';bar.style.cssText=`height:${h}px;background:#93c5fd;border-radius:4px 4px 0 0;cursor:pointer;transition:background 0.15s;`;bar.addEventListener('mouseenter',e=>{bar.style.background='#1152d4';tip.textContent=t.month+': '+t.count+' profiled';tip.style.opacity='1';});bar.addEventListener('mousemove',e=>{tip.style.left=(e.clientX+12)+'px';tip.style.top=(e.clientY-28)+'px';});bar.addEventListener('mouseleave',()=>{bar.style.background='#93c5fd';tip.style.opacity='0';});const MONTH_ABBR=['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],mNum=parseInt(t.month.slice(5),10);const lbl=document.createElement('span');lbl.className='bar-label';lbl.textContent=MONTH_ABBR[mNum]||t.month.slice(5);col.appendChild(vd);col.appendChild(bar);col.appendChild(lbl);container.appendChild(col);});}
  const low=[...RG_DATA.regions].sort((a,b)=>a.rate-b.rate).slice(0,5);
  const lowEl=document.getElementById('rg-low-regions');
  if(lowEl)lowEl.innerHTML=low.length?low.map(r=>`<div style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0.75rem;background:#fff8f8;border-radius:0.5rem;border:1px solid #fecaca;"><div><div style="font-weight:700;font-size:0.8125rem;">${r.region}</div><div style="font-size:0.7rem;color:var(--gray-400);">${r.in_progress} in progress · ${r.total} total</div></div><span style="font-weight:800;color:#ef4444;">${r.rate}%</span></div>`).join(''):'<div style="color:var(--gray-400);font-size:0.75rem;padding:1rem;">No data.</div>';
}

function showRgTab(tab){
  rgCurrentTab=tab;
  document.querySelectorAll('[data-rgtab]').forEach(t=>t.classList.toggle('active',t.dataset.rgtab===tab));
  document.querySelectorAll('[id^="rgtab-"]').forEach(p=>p.style.display='none');
  const panel=document.getElementById('rgtab-'+tab);
  if(panel)panel.style.display='';
  if(tab==='insights')renderRgInsights();
}

function exportRegionsCSV(){
  const headers=['Region','Total Profiled','Completed','In Progress','Completion %','Interviewers','Provinces'];
  const rows=RG_DATA.regions.map(r=>[r.region,r.total,r.completed,r.in_progress,r.rate+'%',r.interviewers,r.province_count]);
  const csv=[headers,...rows].map(row=>row.map(v=>'"'+String(v??'').replace(/"/g,'""')+'"').join(',')).join('\n');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download=`regions-${new Date().toISOString().slice(0,10)}.csv`;a.click();
}

function refreshRegionsView(){rgLoaded=false;loadRegionsView();}

// ── Shared donut renderer ─────────────────────────────────────
function anRenderDonut(elId, slices, centerVal, centerLabel) {
  const el = document.getElementById(elId);
  if (!el) return;
  const total = slices.reduce((s,x)=>s+x.val,0);
  if (!total) { el.innerHTML = AN_EMPTY; return; }
  const circ = 314;
  const uid = elId;
  const tipId = uid + '-tip';
  let offset = 0;
  const segs = slices.map((s,i) => {
    const dash = (s.val/total)*circ;
    const pct = Math.round((s.val/total)*100);
    const safeLabel = s.label.replace(/'/g,"\\'").replace(/"/g,'&quot;');
    const seg = '<circle cx="70" cy="70" r="50" fill="none" stroke="'+s.color+'" stroke-width="20"'
      +' stroke-dasharray="'+dash.toFixed(2)+' '+(circ-dash).toFixed(2)+'"'
      +' stroke-dashoffset="'+(78.5-offset).toFixed(2)+'"'
      +' transform="rotate(-90 70 70)" data-i="'+i+'"'
      +' style="cursor:pointer;transition:stroke-width 0.2s ease,opacity 0.2s ease;"'
      +' onmouseover="(function(c,e){document.querySelectorAll(\'#'+uid+'-svg circle[data-i]\').forEach(function(x){x.style.opacity=\'0.3\';});c.style.opacity=\'1\';c.style.strokeWidth=\'27px\';var tip=document.getElementById(\''+tipId+'\');if(tip){tip.innerHTML=\'<strong>'+safeLabel+'</strong>: '+s.val.toLocaleString()+'  ('+pct+'%)\';tip.style.display=\'block\';tip.style.left=(e.clientX+12)+\'px\';tip.style.top=(e.clientY-10)+\'px\';};})(this,event)"'
      +' onmousemove="(function(e){var tip=document.getElementById(\''+tipId+'\');if(tip){tip.style.left=(e.clientX+12)+\'px\';tip.style.top=(e.clientY-10)+\'px\';}})(event)"'
      +' onmouseout="(function(){document.querySelectorAll(\'#'+uid+'-svg circle[data-i]\').forEach(function(x){x.style.opacity=\'1\';x.style.strokeWidth=\'20px\';});var tip=document.getElementById(\''+tipId+'\');if(tip)tip.style.display=\'none\';})()"'
      +'"/>';
    offset += dash;
    return seg;
  }).join('');
  el.innerHTML = '<div style="position:relative;">'
    +'<svg id="'+uid+'-svg" viewBox="0 0 140 140" width="150" height="150" style="overflow:visible;">'+segs+'</svg>'
    +'<div id="'+tipId+'" style="display:none;position:fixed;background:#1e293b;color:#fff;border-radius:0.5rem;padding:0.4rem 0.75rem;font-size:10px;pointer-events:none;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,0.2);white-space:nowrap;"></div>'
    +'</div>'
    +'<div style="display:flex;flex-wrap:wrap;gap:0.5rem 1rem;justify-content:center;font-size:0.75rem;">'
    +slices.map(s=>'<div style="display:flex;align-items:center;gap:0.3rem;"><div style="width:10px;height:10px;border-radius:50%;background:'+s.color+';flex-shrink:0;"></div>'+s.label+': <b>'+s.val.toLocaleString()+'</b></div>').join('')
    +'</div>';
}

function anRenderHorizBars(elId, items, colorPalette) {
  const el = document.getElementById(elId);
  if (!el) return;
  if (!items || !items.length) { el.innerHTML = AN_EMPTY; return; }
  const maxV = Math.max(...items.map(i=>i.val||i.count||0), 1);
  const colors = colorPalette || ['#1152d4','#2563eb','#3b82f6','#60a5fa','#93c5fd','#1d4ed8','#bfdbfe'];
  const total = items.reduce((s,x)=>s+(x.val||x.count||0),0);
  el.innerHTML = items.map((item,i)=>{
    const v = item.val||item.count||0;
    const pct = total>0?Math.round(v/total*100):0;
    const safeLabel = item.label.replace(/'/g,'&#39;').replace(/"/g,'&quot;');
    return '<div style="display:flex;align-items:center;gap:0.75rem;padding:0.2rem 0.375rem;border-radius:0.375rem;transition:background 0.13s;cursor:default;"'
      +' onmouseover="this.style.background=\'var(--blue-light)\';if(typeof gTip===\'function\')gTip(event,\'<strong>'+safeLabel+'</strong>: '+v.toLocaleString()+' &nbsp;('+pct+'%)\')"'
      +' onmousemove="if(typeof gTipMove===\'function\')gTipMove(event)"'
      +' onmouseout="this.style.background=\'\';if(typeof gTipHide===\'function\')gTipHide()">'
      +'<div style="font-size:0.75rem;font-weight:600;width:190px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+item.label+'</div>'
      +'<div style="flex:1;height:10px;background:var(--gray-200);border-radius:999px;overflow:hidden;"><div style="height:100%;width:'+Math.round((v/maxV)*100)+'%;background:'+colors[i%colors.length]+';border-radius:999px;"></div></div>'
      +'<div style="font-size:0.72rem;font-weight:800;width:32px;text-align:right;">'+v+'</div>'
      +'<div style="font-size:0.7rem;color:var(--gray-400);width:34px;">'+pct+'%</div>'
      +'</div>';
  }).join('');
}

async function renderAnBeneficiaryExtras() {
  await fetchExtendedAnalytics();
  const ext = AN_EXT;
  const fs = ext && ext.family_size;
  if (fs) {
    anRenderDonut('an-family-size-donut', [
      {label:'1-4 Members', val:fs.small||0, color:'#1152d4'},
      {label:'5-7 Members', val:fs.medium||0, color:'#7c3aed'},
      {label:'8+ Members',  val:fs.large||0, color:'#ec4899'}
    ], fs.avg ? fs.avg+' avg' : '-', 'Avg size');
  }
  const ps = ext && ext.fourps;
  const psEl = document.getElementById('an-4ps-chart');
  if (ps && psEl) {
    const psPct = ps.total > 0 ? Math.round((ps.yes / ps.total) * 100) : 0;
    const notPct = 100 - psPct;
    psEl.style.flexDirection = 'column';
    psEl.style.alignItems = '';
    psEl.innerHTML = '<div style="width:100%;padding:0.5rem 0;">'
      +'<div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.875rem;padding:0.5rem 0.75rem;background:#f0fdf4;border-radius:0.5rem;border:1px solid #bbf7d0;">'
      +'<div style="width:2.5rem;height:2.5rem;border-radius:50%;background:#22c55e;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><span class="material-symbols-outlined" style="color:white;font-size:1.2rem;">check_circle</span></div>'
      +'<div style="flex:1;"><div style="font-size:0.8125rem;font-weight:700;color:#15803d;">4Ps Members</div><div style="font-size:0.72rem;color:#16a34a;">'+(ps.yes||0).toLocaleString()+' households</div></div>'
      +'<div style="font-size:1.75rem;font-weight:800;color:#15803d;">'+psPct+'%</div></div>'
      +'<div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem;background:#f9fafb;border-radius:0.5rem;border:1px solid var(--border);">'
      +'<div style="width:2.5rem;height:2.5rem;border-radius:50%;background:#9ca3af;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><span class="material-symbols-outlined" style="color:white;font-size:1.2rem;">remove_circle</span></div>'
      +'<div style="flex:1;"><div style="font-size:0.8125rem;font-weight:700;color:var(--gray-700);">Non-4Ps</div><div style="font-size:0.72rem;color:var(--gray-400);">'+(ps.no||0).toLocaleString()+' households</div></div>'
      +'<div style="font-size:1.75rem;font-weight:800;color:var(--gray-500);">'+notPct+'%</div></div>'
      +'<div style="margin-top:1rem;"><div style="height:12px;border-radius:999px;overflow:hidden;background:#e5e7eb;display:flex;"><div style="width:'+psPct+'%;background:#22c55e;border-radius:999px 0 0 999px;transition:width 0.6s ease;"></div></div>'
      +'<div style="display:flex;justify-content:space-between;margin-top:0.3rem;font-size:0.7rem;color:var(--gray-400);"><span>0%</span><span style="color:var(--gray-500);font-weight:600;">Total: '+(ps.total||0).toLocaleString()+'</span><span>100%</span></div></div></div>';
  }
  const rel = ext && ext.religion;
  if (rel && rel.length) {
    const colors = ['#1152d4','#7c3aed','#ec4899','#f59e0b','#10b981','#9ca3af'];
    anRenderDonut('an-religion-donut', rel.map((r,i)=>({label:r.label, val:r.count, color:colors[i%colors.length]})), rel[0]?rel[0].label:'-', 'Most common');
  }
  const disTypes = (ext && ext.dis_types_ranked) || [];
  anRenderHorizBars('an-dis-type-bars', disTypes.map(d=>({label:d.label, val:d.count})));
  const tbody = document.getElementById('an-dis-type-tbody');
  if (tbody) tbody.innerHTML = disTypes.length
    ? disTypes.map((d,i)=>'<tr><td>'+(i+1)+'</td><td>'+d.label+'</td><td><b>'+d.count.toLocaleString()+'</b></td></tr>').join('')
    : '<tr><td colspan="3" style="text-align:center;color:var(--gray-400);padding:1rem;">No data</td></tr>';
}

async function renderAnHousing() {
  await fetchExtendedAnalytics();
  const h = AN_EXT && AN_EXT.housing;
  if (!h) return;
  anRenderHorizBars('an-housing-materials', h.materials, ['#1152d4','#7c3aed','#ec4899','#f59e0b','#10b981','#ef4444']);
  anRenderHorizBars('an-tenure-status', h.tenure, ['#22c55e','#1152d4','#f59e0b','#ec4899','#9ca3af']);
  anRenderHorizBars('an-water-supply', h.water_source, ['#06b6d4','#1152d4','#7c3aed','#10b981','#f59e0b']);
  anRenderDonut('an-house-mod', [
    {label:'With Modification', val:h.mod_yes||0, color:'#22c55e'},
    {label:'Without', val:h.mod_no||0, color:'#e5e7eb'}
  ], h.mod_total>0?Math.round((h.mod_yes/h.mod_total)*100)+'%':'-', 'Modified');
}

async function renderAnEducationExtra() {
  await fetchExtendedAnalytics();
  anRenderHorizBars('an-educ-attainment', AN_EXT && AN_EXT.educ_attainment, ['#1152d4','#7c3aed','#ec4899','#f59e0b','#10b981','#ef4444','#06b6d4','#9ca3af']);
}

async function renderAnEconomicExtra() {
  await fetchExtendedAnalytics();
  const mw = AN_EXT && AN_EXT.min_wage;
  if (!mw) return;
  anRenderHorizBars('an-min-wage', [
    {label:'Below Minimum Wage', val:mw.below||0},
    {label:'At Minimum Wage',    val:mw.at||0},
    {label:'Above Minimum Wage', val:mw.above||0}
  ], ['#ef4444','#f59e0b','#22c55e']);
}

async function renderAnServicesExtra() {
  await fetchExtendedAnalytics();
  const fa = AN_EXT && AN_EXT.financial_assist;
  if (!fa) return;
  anRenderDonut('an-financial-assist-donut', [
    {label:'Receiving', val:fa.yes||0, color:'#22c55e'},
    {label:'Not Receiving', val:fa.no||0, color:'#e5e7eb'}
  ], fa.total>0?Math.round((fa.yes/fa.total)*100)+'%':'-', 'Receiving');
}

function applyAnSectionVisibility() {
  AN_SECTIONS.forEach(function(s) {
    const show = anSectionVisibility[s.key] !== false;
    if (s.headers) {
      document.querySelectorAll('.an-section-header').forEach(function(h) {
        if (s.headers.includes(h.textContent.trim())) h.style.display = show ? '' : 'none';
      });
    }
    if (s.selector) {
      document.querySelectorAll(s.selector).forEach(function(el) { el.style.display = show ? '' : 'none'; });
    }
    if (s.ids) {
      const cardsSeen = new Set(), gridsSeen = new Set();
      s.ids.forEach(function(id) {
        const el = document.getElementById(id);
        if (!el) return;
        const card = el.closest('.card');
        if (card && !cardsSeen.has(card)) { cardsSeen.add(card); card.style.display = show ? '' : 'none'; }
      });
      s.ids.forEach(function(id) {
        const el = document.getElementById(id);
        if (!el) return;
        const grid = el.closest('[style*="grid-template-columns"]');
        if (!grid || gridsSeen.has(grid)) return;
        gridsSeen.add(grid);
        const cards = Array.from(grid.querySelectorAll(':scope > .card'));
        if (!cards.length) return;
        const allHidden = cards.every(function(c) { return c.style.display === 'none'; });
        grid.style.display = allHidden ? 'none' : '';
        grid.style.marginBottom = allHidden ? '0' : '';
      });
    }
  });
}

function openAnReportSettings() {
  const list = document.getElementById('an-settings-list');
  if (!list) return;
  list.innerHTML = AN_SECTIONS.map(function(s) {
    const on = anSectionVisibility[s.key] !== false;
    const locked = s.locked;
    return '<div style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--gray-100);">'
      +'<div><span style="font-size:0.8125rem;font-weight:600;color:var(--dark);">'+s.label+'</span>'+(locked ? '<span style="font-size:0.65rem;color:var(--gray-400);margin-left:0.4rem;">Always visible</span>' : '')+'</div>'
      +'<div id="an-toggle-'+s.key+'" '+(locked ? '' : 'onclick="toggleAnSection(\''+s.key+'\')"')+' style="width:40px;height:22px;border-radius:999px;background:'+(locked?'#9ca3af':on?'#1152d4':'#e5e7eb')+';position:relative;'+(locked?'cursor:not-allowed;opacity:0.7;':'cursor:pointer;')+'transition:background 0.2s;flex-shrink:0;">'
      +'<div id="an-knob-'+s.key+'" style="width:18px;height:18px;border-radius:50%;background:#fff;position:absolute;top:2px;left:'+(locked||on?'20':'2')+'px;transition:left 0.2s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></div></div></div>';
  }).join('');
  document.getElementById('an-report-settings-modal').style.display = 'flex';
}

function toggleAnSection(key) {
  anSectionVisibility[key] = !anSectionVisibility[key];
  const on = anSectionVisibility[key];
  const toggle = document.getElementById('an-toggle-' + key);
  const knob   = document.getElementById('an-knob-'   + key);
  if (toggle) toggle.style.background = on ? '#1152d4' : '#e5e7eb';
  if (knob)   knob.style.left = on ? '20px' : '2px';
}

function saveAnReportSettings() {
  applyAnSectionVisibility();
  closeAnReportSettings();
  if (typeof showGlobalToast === 'function') showGlobalToast('Analytics view saved.', 'success');
}

function closeAnReportSettings() {
  const m = document.getElementById('an-report-settings-modal');
  if (m) m.style.display = 'none';
}
