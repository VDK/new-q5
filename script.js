var image_root = "http://commons.wikimedia.org/wiki/Special:FilePath/";
$(function(){



(function(){


  /**  notable people disclaimer **/
  const $notice = $('#notice');
  const $reopen = $('#reopen');

  function apply(state){
    if (state === 'closed') { $notice.hide(); $reopen.show(); }
    else { $notice.show(); $reopen.hide(); }
  }

  function send(action){
    $.ajax({
      type: 'GET',
      url: 'controllers/notice_controller.php',
      data: { action, ajax: 1 }
    }).always(function(){
      // persist UI state locally regardless of server response
      localStorage.setItem('noticeState', action === 'close' ? 'closed' : 'open');
      apply(localStorage.getItem('noticeState'));
    });
  }

  // initial state (default open)
  apply(localStorage.getItem('noticeState') || 'open');

  // delegate to document so it works if buttons are added/replaced later
  $(document).on('click', '#close', function(e){
    e.preventDefault();
    send('close');
  });
  $(document).on('click', '#reopen', function(e){
    e.preventDefault();
    send('reopen');
  });
})();

/** send QS to the QS app **/

window.sendQS = function sendQS(){
  let qs  = $('#quickstatement').val().trim();
  qs = qs.split(/\n/).join('||');
  qs = encodeURIComponent(qs);
  const url = "https://quickstatements.toolforge.org/#v1=" + qs;
  const win = window.open(url, '_blank');
  if (win) win.focus();
};




   
 /**  Citoid AJAX **/
  const $input = $('#ref_url');            // your URL input
  const $status = $('#citoid-status');     // optional status element
  let pendingReq = null;
  let timer = null;

  function clearOutput() {
    // wipe your UI fields that show title/lang/authors/pubdate
    $('#ref_title, #ref_lang, #ref_authors, #ref_pubdate').val('');
    $status && $status.text('');
  }

  function fetchCitoid(url) {
    // cancel previous request, if any
    if (pendingReq && pendingReq.readyState !== 4) {
      pendingReq.abort();
    }

    pendingReq = $.getJSON('controllers/citoid_controller.php', { url })
      .done(function (data) {
        if (data && !data.error) {
          $('#ref_title').val(data.title || '');
          $('#ref_lang').val(data.language || '');
          $('#ref_authors').val((data.authors || []).join(', '));
          $('#ref_pubdate').val(data.pubdate || '');
          $status && $status.text('');
        } else {
          clearOutput();
          $status && $status.text(data.error || 'No metadata found');
        }
      })
      .fail(function (xhr) {
        // Ignore aborts (they’re expected when typing/clearing)
        if (xhr.statusText === 'abort') return;
        clearOutput();
        $status && $status.text('Citoid fetch failed');
      });
  }

  function scheduleFetch(raw) {
    // debounce keystrokes
    clearTimeout(timer);
    timer = setTimeout(function () {
      const url = raw.trim();

      // hard stop: empty or not a plausible URL → no request
      if (!url) { 
        clearOutput();
        return;
      }
      // optional lightweight check; adjust to your needs
      if (!/^https?:\/\//i.test(url)) {
        // no request; maybe show a hint
        return;
      }

      fetchCitoid(url);
    }, 300);
  }

  // Trigger on input changes (typing, paste, clear)
  $input.on('input', function () {
    scheduleFetch(this.value);
  });

  // Also handle manual clear button, if you have one
  $('#clear-ref-url').on('click', function (e) {
    e.preventDefault();
    $input.val('');
    clearOutput();
    if (pendingReq && pendingReq.readyState !== 4) pendingReq.abort();
  });





   /**  Get search result for name matches AJAX **/
  
// tiny helper
function esc(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }


/** select match with existing Wikidata item **/
// Keep your selectOption unchanged
function selectOption() {
  $('#person_QID').val($(this).attr('qid'));
  const previouslySelected = $('#responses').find('.selected')[0];
  $(previouslySelected).removeClass('selected').addClass('option');
  $(this).addClass('selected').removeClass('option');
}

// ---- caching / indexing ----
// --- globals ---
const activePids          = new Set();   // all PIDs in use
const activeExternalPids  = new Set();   // subset: external-id PIDs
const extInputByPid       = new Map();   // PID -> <input> (external only)

// --- tiny helpers; adapt selectors to your markup ---
function getRowPid($row){
  return String($row.attr('data-pid') || $row.find('.prop_pid').val() || '').toUpperCase();
}
function rowIsExternal($row){
  // Prefer a data-flag you set when toggling; fall back to visibility check:
  return $row.attr('data-type') === 'external' ||
         $row.find('[id^="pv_value_ext_"]:visible').length > 0;
}
function getRowExternalInput($row){
  return $row.find('[id^="pv_value_ext_input_"]')[0] || null;
}

function recomputeActiveIndex(){
  activePids.clear();
  activeExternalPids.clear();
  extInputByPid.clear();

  $('#pv_list .pv-row').each(function(){
    const $r = $(this);
    const pid = getRowPid($r);
    if (!/^P\d+$/.test(pid)) return;

    activePids.add(pid);
    if (rowIsExternal($r)) {
      activeExternalPids.add(pid);
      const el = getRowExternalInput($r);
      if (el) extInputByPid.set(pid, el);
    }
  });

  syncURLtoCurrentPIDs();
}

function currentPIDs(){
  return Array.from(activePids);
}

// debounce to avoid thrash during batch ops (like reindex)
const syncURLtoCurrentPIDs = debounce(() => {
  const p = Array.from(activePids).join('|');
  const url  = new URL(location.href);
  if (activePids.size) url.searchParams.set('property', p);
  else url.searchParams.delete('property');
  history.replaceState(null, '', url.toString());
}, 100);

// simple debounce utility
function debounce(fn, wait){
  let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}




// helper: collect all external-ID PIDs currently present in the form
function getActiveExternalPids() {
  return Array.from(activeExternalPids);
}

function esc(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

$('#fullname').on('change', function () {
  const term = $(this).val().trim();
  const extp = getActiveExternalPids();            // ← pass the P’s in use
  $.getJSON('controllers/query.php', { srsearch: term, extp: extp.join(',') })
    .done(handleSearchResponse)
    .fail(err => console.error(err));
});


let lastResults = [];

function handleSearchResponse (data){
  lastResults = Array.isArray(data) ? data : [];
  if (!lastResults.length) return;

  $("#possible_match").show();
  const $responses = $('#responses').empty();

  const $new = $('<li/>', { class:'block-2 selected', text:'New item' }).on('click', selectOption);
  $responses.append($new[0]);

  const frag = document.createDocumentFragment();
  for (const item of lastResults) {
    const li = document.createElement('li');
    li.className = 'block-2 option';
    li.setAttribute('qid', item.qitem);
    if (item.imageThumb){
      li.style.backgroundImage = `url('${item.imageThumb}')`;
      li.style.backgroundRepeat = 'no-repeat';
      li.style.backgroundPosition = 'left top';
      li.setAttribute('image','true');
    }
    let html = `<a target="_blank" href="https://wikidata.org/wiki/${esc(item.qitem)}">${esc(item.itemLabel || item.qitem)}</a>`;
    if (item.dateOfBirth || item.dateOfDeath){
      const y1 = item.dateOfBirth ? new Date(item.dateOfBirth).getFullYear() : '';
      const y2 = item.dateOfDeath ? new Date(item.dateOfDeath).getFullYear() : '';
      html += ` (${esc(y1)}${y2 ? '-' + esc(y2) : ''})`;
    }
    if (item.description_suggest_en_noun) html += ' ' + esc(item.description_suggest_en_noun);
    li.innerHTML = html;
    li.addEventListener('click', selectOption);
    frag.appendChild(li);
  }
  $responses[0].appendChild(frag);

  tryAutoSelectFromForm();
}

function tryAutoSelectFromForm() {
  if (!lastResults.length || !activeExternalPids.size) return;

  // read values directly from cached elements
  const wanted = {};
  for (const pid of activeExternalPids) {
    const el = extInputByPid.get(pid);
    if (!el) continue;
    const v = (el.value || '').trim();
    if (v) wanted[pid] = v;
  }
  const pids = Object.keys(wanted);
  if (!pids.length) return;

  let best = null, bestScore = 0;
  for (const item of lastResults) {
    const ext = item.external || {};
    let s = 0;
    for (const pid of pids) if ((ext[pid] || []).includes(wanted[pid])) s++;
    if (s > bestScore) { best = item; bestScore = s; }
    if (bestScore === pids.length) break; // perfect match
  }

  if (best) {
    const $li = $(`#responses li[qid="${best.qitem}"]`);
    if ($li.length) $li.trigger('click');
  }
}




$('#age').on('keyup', function(){
  this.value = this.value.replace(/[^0-9]/gi, '');
});


   






  const LANG    = document.documentElement.getAttribute('lang') || 'en';
  const IMDb_PID =  'P345';
  const $list   = $('#pv_list');

  /* ------------ external-id normalizers (by PID) ------------ */
// One registry used everywhere

const NORMALIZERS = {
  // IMDb (only humans)
  'P345': s => {
    const m = s.match(/(?:https?:\/\/)?(?:www\.)?imdb\.com\/name\/(nm\d{7,8})\/?/i)
           || s.match(/\b(nm\d{7,8})\b/i);
    return m ? m[1].toLowerCase() : s.trim();
  },
  // ORCID
  'P496': s => {
    s = s.trim();
    const m = s.match(/(?:orcid\.org\/)?(\d{4}-\d{4}-\d{4}-\d{3}[0-9Xx])/);
    return (m ? m[1] : s.replace(/\s+/g,'')).toUpperCase();
  },
  // VIAF
  'P214': s => {
    const m = s.match(/(?:viaf\.org\/viaf\/)?(\d{6,})/i);
    return m ? m[1] : s.trim();
  },
  // ISNI
  'P213': s => {
    const m = s.match(/(?:isni\.org\/(?:isni\/)?)?([\d Xx-]{15,19})/);
    const id = m ? m[1] : s;
    return id.replace(/[\s-]+/g,'').toUpperCase(); // 16 chars, no dashes
  },
};

// Shared utility for all external-id inputs
function normalizeExternal(pid, raw){
  let s = (raw || '').trim();
  if (!s) return s;
  if (NORMALIZERS[pid]) return NORMALIZERS[pid](s);

  // generic fallback: keep last URL segment, drop query/fragment
  if (/^https?:\/\//i.test(s)) {
    try {
      const u = new URL(s);
      const seg = u.pathname.split('/').filter(Boolean).pop() || '';
      return seg.replace(/[?#].*$/,'');
    } catch(e) { /* ignore */ }
  }
  return s;
}



  /* ------------ row utilities ------------ */

  // snapshot the first row as a template
  const $template = $list.find('.pv-row').first().clone(false, false);

  function setId($row, oldId, newId){
    let $el = $row.find('#' + oldId);
    if (!$el.length) $el = $row.find(`[id^="${oldId}_"]`).first();
    if ($el.length) $el.attr('id', newId);
    $row.find(`label[for="${oldId}"]`).attr('for', newId);
    $row.find(`label[for^="${oldId}_"]`).attr('for', newId);
  }

  // ensure hidden ID fields exist (so we submit IDs, not labels)
  function ensureHiddenInputs($row, idx){
    if (!$row.find(`input[type="hidden"].prop_pid_${idx}`).length)
      $row.append(`<input type="hidden" class="prop_pid prop_pid_${idx}" name="pv[${idx}][p]">`);
    if (!$row.find(`input[type="hidden"].value_qid_${idx}`).length)
      $row.append(`<input type="hidden" class="value_qid value_qid_${idx}" name="pv[${idx}][v]">`);
    if (!$row.find(`input[type="hidden"].ext_val_${idx}`).length)
      $row.append(`<input type="hidden" class="ext_val ext_val_${idx}" name="pv[${idx}][ext]">`);
  }

  function renumberRow($row, idx, keepValues){
    // IDs & names for visible inputs (remove name so they don't submit labels)
    setId($row, 'pv_prop',              `pv_prop_${idx}`);
    setId($row, 'pv_prop_results',      `pv_prop_results_${idx}`);
    setId($row, 'pv_prop_meta',         `pv_prop_meta_${idx}`);
    $row.find(`#pv_prop_${idx}`).removeAttr('name');

    setId($row, 'pv_value_item',        `pv_value_item_${idx}`);
    setId($row, 'pv_value_q',           `pv_value_q_${idx}`);
    setId($row, 'pv_value_results',     `pv_value_results_${idx}`);
    setId($row, 'pv_value_meta',        `pv_value_meta_${idx}`);
    $row.find(`#pv_value_q_${idx}`).removeAttr('name');

    setId($row, 'pv_value_ext',         `pv_value_ext_${idx}`);
    setId($row, 'pv_value_ext_input',   `pv_value_ext_input_${idx}`);
    $row.find(`#pv_value_ext_input_${idx}`).removeAttr('name');

    setId($row, 'pv_ref_0',             `pv_ref_${idx}`);
    $row.find(`#pv_ref_${idx}`).attr('name', `pv[${idx}][ref]`);

    $row.find('.prop_pid, .value_qid, .ext_val').remove();
    ensureHiddenInputs($row, idx);

    // clear values & UI
    if (!keepValues) {
      $row.find('input[type="text"]').val('').removeClass('pv-picked');
      $row.find('.pv-suggest').hide().empty();
      $row.find(`#pv_ref_${idx}`).prop('checked', true);
      $row.find(`#pv_value_ext_${idx}`).attr('hidden', true);
      $row.find(`#pv_value_item_${idx}`).attr('hidden', false);
    }
  }



    function toggleValueUI($row, idx, isExternal, suppressFocus){
      const $item = $row.find(`#pv_value_item_${idx}`);
      const $ext  = $row.find(`#pv_value_ext_${idx}`);

      if (isExternal) {
        $item.prop('hidden', true);
        $ext.prop('hidden', false);
        $row.find(`.value_qid_${idx}`).val('');
        $row.find(`#pv_value_q_${idx}`).val('').removeClass('pv-picked');
        $row.find(`#pv_ref_${idx}`).prop('checked', false);
        if (!suppressFocus) $row.find(`#pv_value_ext_input_${idx}`).trigger('focus');
        $row.attr('data-type','external');        // ← keep DOM state in sync
      } else {
        $ext.prop('hidden', true);
        $item.prop('hidden', false);
        $row.find(`.ext_val_${idx}`).val('');
        $row.find(`#pv_value_ext_input_${idx}`).val('').removeClass('pv-picked');
        $row.find(`#pv_ref_${idx}`).prop('checked', true);
        if (!suppressFocus) $row.find(`#pv_value_q_${idx}`).trigger('focus');
        $row.attr('data-type','item');            // ← keep DOM state in sync
      }
      $row.attr('data-type', isExternal ? 'external' : 'item');

      const pid = String($row.attr('data-pid') || $row.data('pid') || '').trim();
      const inputEl = $row.find(`#pv_value_ext_input_${idx}`)[0] || null; // cache input
    }





  /* ------------ typeahead (per input) ------------ */
  function bindSuggest($input, $listEl, endpoint, onPick){
    let timer = null, lastQ = '', activeIdx = -1, items = [];

    function close(){ $listEl.hide().empty(); $input.attr('aria-expanded','false'); items=[]; activeIdx=-1; }
    function setActive(i){
      if (!items.length) return;
      if (i < 0) i = items.length - 1;
      if (i >= items.length) i = 0;
      activeIdx = i;
      $listEl.children().removeClass('is-active')
        .eq(activeIdx).addClass('is-active')
        .get(0).scrollIntoView({ block: 'nearest' });
    }
    function render(data){
      items = data || [];
      $listEl.empty();
      if (!items.length) return close();
      items.forEach(o=>{
        const idBadge = o.id || o.qid || o.qitem || o.value || o.q || '';
        const $li = $('<li/>').addClass('pv-option').attr('role','option')
          .append($('<span class="pv-id"/>').text(idBadge))
          .append($('<div class="pv-label"/>').text(o.label || ''))
          .append($('<div class="pv-desc"/>').text(o.description || ''))
          .on('mousedown', e => { e.preventDefault(); onPick(o); close(); });
        $listEl.append($li);
      });
      activeIdx = 0;
      $listEl.children().eq(0).addClass('is-active');
      $listEl.show(); $input.attr('aria-expanded','true');
    }


    $input.off('input.pv keydown.pv blur.pv');

    $input.on('input.pv', function(){
      const q = $(this).val().trim();
      $(this).removeClass('pv-picked'); // typing means not confirmed
      if (q.length < 2 || q === lastQ){ close(); return; }
      lastQ = q;
      clearTimeout(timer);
      timer = setTimeout(()=>{
        $.getJSON('controllers/query.php', { pv: endpoint, q, lang: LANG })
          .done(render)
          .fail(close);
      }, 140);
    });

    $input.on('keydown.pv', function(e){
      if (!$listEl.is(':visible')) return;
      if (e.key === 'ArrowDown'){ e.preventDefault(); setActive(activeIdx + 1); }
      else if (e.key === 'ArrowUp'){ e.preventDefault(); setActive(activeIdx - 1); }
      else if (e.key === 'Enter'){
        e.preventDefault();
        if (items[activeIdx]) { onPick(items[activeIdx]); close(); }
      } else if (e.key === 'Escape'){ e.preventDefault(); close(); }
    });

    $input.on('blur.pv', ()=> setTimeout(close, 120));
  }



  function wireRow($row, idx){
    const $pIn   = $row.find(`#pv_prop_${idx}`);
    const $pList = $row.find(`#pv_prop_results_${idx}`);
    const $pMeta = $row.find(`#pv_prop_meta_${idx}`);

    const $qIn   = $row.find(`#pv_value_q_${idx}`);
    const $qList = $row.find(`#pv_value_results_${idx}`);
    const $qMeta = $row.find(`#pv_value_meta_${idx}`);

    // ---- Property search (datatype comes from server) ----
    const onPickProp = function(p){
      // p.id = PID, p.label = label, p.datatype = wikibase datatype
      const pickedPid = String(p.id || '').toUpperCase();

      // Guard: allow only one IMDb (P345) in the entire form
      if (pickedPid === IMDb_PID && formAlreadyHasIMDb($row.get(0))) {
        $pMeta.text('IMDb (P345) is already present — only one allowed.');
        $pIn.val('').removeClass('pv-picked');
        return;
      }

      $pIn.val(p.label || p.id).addClass('pv-picked');
      $row.attr('data-pid', pickedPid);   
      $row.find(`.prop_pid_${idx}`).val(p.id);
      $pMeta.text('');

      const isExt = p.datatype === 'external-id';
      toggleValueUI($row, idx, isExt, false);

      const advance = !$pIn.data('noAdvance');
      if (advance) {
        if (isExt) $row.find(`#pv_value_ext_input_${idx}`).trigger('focus');
        else $qIn.trigger('focus');
      }
      $pIn.data('noAdvance', false);
      recomputeActiveIndex();
    };

    bindSuggest($pIn, $pList, 'propsearch', onPickProp);

    // ---- Q-item search ----
    bindSuggest($qIn, $qList, 'itemsearch', function(q){
      const qid = q.id || q.qid || q.qitem || q.value || q.q;
      $qIn.val(q.label || qid).addClass('pv-picked').data('pickedId', qid);
      $row.find(`input[name="pv[${idx}][v]"]`).val(qid);
      $qMeta.text('');
    });

    // Allow raw QID typed; mark picked if it matches Q\d+
    $qIn.off('change.pv').on('change.pv', function(){
      const raw = $(this).val().trim();
      const ok = /^Q\d+$/i.test(raw);
      $row.find(`.value_qid_${idx}`).val(ok ? raw : '');
      $(this).toggleClass('pv-picked', ok);
      $qMeta.text('');
    });

    // ---- External input: normalize + (if IMDb) fetch + store hidden ----
    wireExternalIdField($row, idx);
  }


  /* ------------ boot + prefill + add row ------------ */

  // prepare first row (index 0)
  const $first = $list.find('.pv-row').first();
  renumberRow($first, 0);
  wireRow($first, 0);



  // Add statement
  $('#pv_add_row').off('click.pv').on('click.pv', function(){
    const idx = $list.find('.pv-row').length;
    const $clone = $template.clone(false, false);
    renumberRow($clone, idx, false);
    $list.append($clone);
    wireRow($clone, idx);
  });

  //try to help if there wasn't a selection made 

  $('form').on('submit', function(){
  $('#pv_list .pv-row').each(function(i, row){
    const $r = $(row);
    const $hidV = $r.find(`input[name="pv[${i}][v]"]`);
    const raw   = $r.find(`#pv_value_q_${i}`).val()?.trim() || '';
    const picked= $r.find(`#pv_value_q_${i}`).data('pickedId');

    if (!$hidV.val()) {
      if (/^Q\d+$/.test(raw))      $hidV.val(raw);
      else if (picked)             $hidV.val(picked);
    }
  });
});

function reindexAllRows(){
  const $rows = $('#pv_list .pv-row');
  $rows.each(function(i){
    const $r = $(this);
    // wipe any old dynamic event handlers before rewiring
    $r.find('input, .pv-suggest').off('.pv');
    renumberRow($r, i, true);
    wireRow($r, i);
  });
  recomputeActiveIndex();
}



// Remove handler (delegated so it works for newly added rows)
$(document).off('click.pv', '.pv-remove').on('click.pv', '.pv-remove', function(){
  const $row = $(this).closest('.pv-row');
  $row.remove();
  reindexAllRows();
  syncURLtoCurrentPIDs && syncURLtoCurrentPIDs();
});



/* ===== Server-prefill + URL stickiness for P only ===== */

// Ensure first row is renumbered/wired once
function ensureFirstRowReady(){
  const $listEl = $('#pv_list');
  const $first  = $listEl.find('.pv-row').first();
  // If it still has base IDs, give it index 0 and wire it
  if ($first.find('#pv_prop').length) {
    renumberRow($first, 0);
    wireRow($first, 0);
  }
}

// Apply [{id,label,datatype}] to rows (no values)
function populatePVFromProps(props){
  if (!Array.isArray(props) || !props.length) return;

  const $listEl = $('#pv_list');
  ensureFirstRowReady();

  function applyOne($row, idx, meta){
    const $pIn   = $row.find(`#pv_prop_${idx}`);
    const $pList = $row.find(`#pv_prop_results_${idx}`);

    // 1) label + hidden PID
    $pIn.val(meta.label || meta.id).addClass('pv-picked').data('noAdvance', true);
    $row.find(`.prop_pid_${idx}`).val(meta.id);

    $row.attr('data-pid', meta.id);        // ← critical (P###)

    // 3) flip value UI (this will also update activeExternalPids via toggleValueUI)
    const isExt = meta.datatype === 'external-id';
    toggleValueUI($row, idx, isExt, true); // suppressFocus = true during prefill

    // 4) close stale suggestions
    $pList.hide().empty();
    $pIn.attr('aria-expanded','false');
  }

  // 1) apply to the first existing row
  applyOne($listEl.find('.pv-row').eq(0), 0, props[0]);

  // 2) create + wire + apply the rest
  for (let i = 1; i < props.length; i++){
    const idx  = $listEl.find('.pv-row').length;
    const $row = $template.clone(false, false);
    renumberRow($row, idx);
    $listEl.append($row);
    wireRow($row, idx);
    applyOne($row, idx, props[i]);
  }

  recomputeActiveIndex();

  $('#fullname').focus();
}






// --- Boot: populate from server-provided data ---
$(function(){
  if (Array.isArray(window.PREFILL_PROPS) && window.PREFILL_PROPS.length){
    populatePVFromProps(window.PREFILL_PROPS);
  } else {
    // No GET ?property= → leave as-is (no persistence of other fields)
  }
});

// Save stickiness only when P’s change
$(document)
  .on('click', '#pv_add_row, .pv-remove', function(){
    // let DOM update, then sync
    setTimeout(syncURLtoCurrentPIDs, 0);
  })
  // after your existing prop-pick callback runs it adds .pv-picked; hook that
  .on('input blur', '.pv-prop input[id^="pv_prop_"]', function(){
    if ($(this).hasClass('pv-picked')) syncURLtoCurrentPIDs();
  });





/* IMDB special script */
function formAlreadyHasIMDb(exceptRow) {
  // if the current row is being edited, temporarily ignore its own PID
  if (!exceptRow) return activePids.has(IMDb_PID);

  const pidHere = getRowPid($(exceptRow));
  if (pidHere === IMDb_PID) {
    // we’re editing the IMDb row — it's fine to keep one
    return false;
  }
  // otherwise just check if any row already registered IMDb
  return activePids.has(IMDb_PID);
}

// Minimal PID → human label map (extend as needed)
const PID_LABELS = {
  'P106' : 'occupation',
  'P27'  : 'country of citizenship',
  'P19'  : 'place of birth',
  'P20'  : 'place of death',
  'P21'  : 'sex or gender',
  'P734' : 'family name',
  'P735' : 'given name',
  // add more…
};

function addStatement(pid, qid, label, opts = {}) {
  if (!pid || !qid) return;
  const { withRef = false, heuristic = false } = opts;

  // 1) Avoid duplicates
  let exists = false;
  $('#pv_list .pv-row').each(function (i, r) {
    const pVal = $(r).find(`.prop_pid_${i}`).val();
    const vVal = $(r).find(`.value_qid_${i}`).val();
    if (pVal === pid && vVal === qid) { exists = true; return false; }
  });
  if (exists) return;

  // 2) Find empty row or clone a new one
  let $free = null;
  $('#pv_list .pv-row').each(function (i, r) {
    const pVal = $(r).find(`.prop_pid_${i}`).val();
    const vVal = $(r).find(`.value_qid_${i}`).val();
    if (!pVal && !vVal && !$free) $free = $(r);
  });
  if (!$free) {
    const idxNew = $('#pv_list .pv-row').length;
    const $clone = $template.clone(false, false);
    renumberRow($clone, idxNew, false);
    $('#pv_list').append($clone);
    wireRow($clone, idxNew);
    $free = $clone;
  }

  const i2 = $free.index();

  // 3) Set property (P) — lock if heuristic
  const propLabel = PID_LABELS[pid] || pid;
  $free.find(`#pv_prop_${i2}`)
       .val(propLabel)
       .addClass('pv-picked')
       .prop('readonly', heuristic)           // lock P if heuristic
       .toggleClass('pv-locked', heuristic);  // (optional) style hook
  $free.find(`.prop_pid_${i2}`).val(pid);

  // Always force Q-item UI for heuristic adds (and generally for these)
  toggleValueUI($free, i2, false, true);      // (stringMode=false, itemMode=true)

  // 4) Set value (label shown, QID hidden)
  $free.find(`#pv_value_q_${i2}`)
       .val(label)
       .addClass('pv-picked')
       .data('pickedId', qid);
  $free.find(`.value_qid_${i2}`).val(qid);

  // 5) Reference checkbox: set from withRef (true/false)
  $free.find(`input[name="pv[${i2}][ref]"]`).prop('checked', !!withRef);

  // (Optional) mark row meta for later logic
  if (heuristic) $free.attr('data-heuristic', '1');
  $free.attr('data-pid', pid);        // keep DOM state in sync
  recomputeActiveIndex();             // refresh activePids + URL

}



function wireExternalIdField($row, idx){
  const $extIn = $row.find(`#pv_value_ext_input_${idx}`);
  const $hidden= $row.find(`.ext_val_${idx}`);
  let imdbTimer = null, lastNm = '';

  function applyIMDbDataToForm(data){
    if (!data || data.ok !== true) return;

    // full name (only if empty)
    if (!$('#fullname').val().trim() && data.displayName) {
      $('#fullname').val(data.displayName).trigger('change');
    }

      // description (only if empty)
    if (!$('#description').val().trim() && data.description_suggest_en_noun) {
      $('#description').val(data.description_suggest_en_noun);
    }

    if (data.description_suggest_en_demonym) {
      $('#description_suggestion').val(data.description_suggest_en_demonym);
    }


    if (Array.isArray(data.alias_candidates) && data.alias_candidates.length) {
      showAliasSuggestions(data.alias_candidates);
    }

    
    // populate P106 rows (no duplicates)
    (data.p106 || []).forEach(p => addStatement('P106', p.qid, p.label || p.qid, {
      withRef: false,
      heuristic: false
    }));

    // Add P27 exactly once (dedupe handled inside addStatement)
    if (data.nationality_qid) {
      addStatement(
        'P27',
        data.nationality_qid,
        data.nationality_label,
        { withRef: false, heuristic: false }
      );
    }



  }



    $extIn.off('input.pv').on('input.pv', function(){
      const pid = $row.find(`.prop_pid_${idx}`).val() || '';
      const normalized = normalizeExternal(pid, this.value || '');
      if (this.value !== normalized) this.value = normalized;

      $hidden.val(normalized);
      $(this).toggleClass('pv-picked', !!normalized);

      // IMDb auto-fetch (nm1234567)
      if (pid === IMDb_PID) {
        const m = normalized.match(/^nm\d{7,8}$/i);
        const nm = m ? m[0].toLowerCase() : '';
        clearTimeout(imdbTimer);
        if (nm && nm !== lastNm) {
          imdbTimer = setTimeout(function(){
            lastNm = nm;
            $.getJSON('controllers/query.php', { endpoint: 'imdb', id: nm })
              .done(applyIMDbDataToForm)
              .fail(()=>{ /* noop */ });
          }, 300);
        }
      }
    });
  }



});


function showAliasSuggestions(aliases) {
  const $target = $('#alias-suggest-container').empty();
  if (!Array.isArray(aliases) || !aliases.length) return;

  const seen = new Set();
  const fullName = ($('#fullname').val() || '').trim();

  const clean = aliases.filter(a => {
    const s = (a || '').trim();
    if (!s) return false;
    if (fullName && s.localeCompare(fullName, undefined, {sensitivity:'accent'}) === 0) return false;
    const key = s.toLocaleLowerCase();
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
  if (!clean.length) return;

  const $panel = $(`
    <div class="alias-panel" id="alias-panel" role="region" aria-label="Suggested aliases">
      <h2 class="alias-title">Suggested aliases:</h2>
      <div class="alias-options"></div>
    </div>
  `);

  const $opts = $panel.find('.alias-options');
  clean.forEach((a, i) => {
    // Wrap <input> inside <label> – simpler, no id/for needed
    const $label = $('<label class="alias-option">');
    const $cb = $('<input>', {
      type: 'checkbox',
      class: 'alias-check',
      name: 'aliases_en[]',   // <-- native submit
      value: a,
      checked: true
    });
    $label.append($cb, document.createTextNode(a));
    $opts.append($label);
  });

  $target.append($panel);
}




