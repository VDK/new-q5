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
      url: 'notice_controller.php',
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


/** select match with existing Wikidata item **/
function selectOption() {
  $('#person_QID').val($(this).attr('qid'));
  const previouslySelected = $('#responses').find('.selected')[0];
  $(previouslySelected).removeClass('selected').addClass('option');
  $(this).addClass('selected').removeClass('option');
}

   
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

    pendingReq = $.getJSON('citoid_controller.php', { url })
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
  
    $('#fullname').on('change', function(){
      $.getJSON( 'query.php', {
        srsearch: $(this).val()
      }).done(function( data ) {
        if (data != "nee"){
          $("#possible_match").show();
          $('#responses').empty();
          var new_item = document.createElement('li');
          new_item.className = "block-2 selected";
          new_item.innerHTML = "New item";
          new_item.onclick = selectOption;
          $('#responses').append(new_item);
          for (var i = 0; i <= data.length - 1; i++) {
            var item = data[i];
            var option = document.createElement('li');
            option.className = "block-2 option";
            option.innerHTML = "";
            if (item['image']) {
              var imageUrl = image_root + encodeURIComponent(item['image']) + "?width=100";
              option.style.backgroundImage = "url('" + imageUrl + "')";
              option.style.backgroundRepeat = "no-repeat";
              option.style.backgroundPosition = "left top";
              option.setAttribute('image', true);
            }
            option.innerHTML += "<a href='https://wikidata.org/wiki/"+item['qitem']+"' target='_blank'>"+item['itemLabel']+"</a>";
            if(item['dateOfBirth'] || item['dateOfDeath']){
              option.innerHTML += " (";
              if (item['dateOfBirth']){
                var d = new Date(item['dateOfBirth']);
                option.innerHTML += d.getFullYear();

              }
              if(item['dateOfDeath']){
                var d = new Date(item['dateOfDeath']);
                option.innerHTML += "-"+ d.getFullYear();
              }
              option.innerHTML += ")"
            }
            if(item['occupation']){
              option.innerHTML += ", "+ item['occupation'] ;
            }
            if(item['country']){
              option.innerHTML += " from "+ item['country'] ;
            }
            option.setAttribute('qid', item['qitem']);
            option.onclick = selectOption;
            $('#responses').append(option);
          }
        }
      });
    });


    $('#age').on('keyup', function(){
      this.value = this.value.replace(/[^0-9]/gi, '');
    });


   






  const LANG    = document.documentElement.getAttribute('lang') || 'en';
  const IMDbPID = $('#pv_list').data('imdbPid') || 'P345';
  const $list   = $('#pv_list');

  /* ------------ external-id normalizers (by PID) ------------ */
   const NORMALIZERS = {
    'P345': s => { // IMDb
      const m = s.match(/(?:imdb\.com\/name\/(nm\d{7,8}))|(?:imdb\.com\/title\/(tt\d{7,8}))|^((?:nm|tt)\d{7,8})/i);
      return m ? (m[1] || m[2] || m[3]) : s;
    },
    'P496': s => { // ORCID
      const m = s.match(/(?:orcid\.org\/)?(\d{4}-\d{4}-\d{4}-\d{3}[0-9Xx])/);
      return m ? m[1].toUpperCase() : s.replace(/\s+/g,'').toUpperCase();
    },
    'P214': s => { // VIAF
      const m = s.match(/(?:viaf\.org\/viaf\/)?(\d{6,})/);
      return m ? m[1] : s;
    },
    'P213': s => { // ISNI
      const m = s.match(/(?:isni\.org\/(?:isni\/)?)?([\d Xx-]{15,19})/);
      return m ? m[1].replace(/[\s-]+/g,'').toUpperCase() : s.replace(/[\s-]+/g,'').toUpperCase();
    },
  };

  function normalizeExternal(pid, raw){
    let s = (raw || '').trim();
    if (NORMALIZERS[pid]) return NORMALIZERS[pid](s);
    // generic: last path segment of URL
    try {
      if (/^https?:\/\//i.test(s)) {
        const u = new URL(s);
        const seg = u.pathname.split('/').filter(Boolean).pop() || '';
        return seg.replace(/[?#].*$/,'');
      }
    } catch(e){}
    return s;
  }

  function wireExternalInput($row, idx){
    const $extIn = $row.find(`#pv_value_ext_input_${idx}`);
    $extIn.off('input.pv').on('input.pv', function(){
      const pid = $row.find(`.prop_pid_${idx}`).val();
      const norm = normalizeExternal(pid || '', this.value || '');
      if (norm !== this.value) this.value = norm;
      $row.find(`.ext_val_${idx}`).val(norm);
      $(this).toggleClass('pv-picked', !!norm);
    });
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
      $item.attr('hidden', true);
      $ext.attr('hidden', false);
      $row.find(`.value_qid_${idx}`).val('');
      $row.find(`#pv_value_q_${idx}`).val('').removeClass('pv-picked');
      // default: external → turn OFF reference
      $row.find(`#pv_ref_${idx}`).prop('checked', false);
      if (!suppressFocus) $row.find(`#pv_value_ext_input_${idx}`).trigger('focus');
    } else {
      $ext.attr('hidden', true);
      $item.attr('hidden', false);
      $row.find(`.ext_val_${idx}`).val('');
      $row.find(`#pv_value_ext_input_${idx}`).val('').removeClass('pv-picked');
      // item-valued → turn ON reference by default
      $row.find(`#pv_ref_${idx}`).prop('checked', true);
      if (!suppressFocus) $row.find(`#pv_value_q_${idx}`).trigger('focus');
    }
  }

  function wireImdbAndExternalTrim($row, idx){
    const $extIn = $row.find(`#pv_value_ext_input_${idx}`);
    $extIn.off('input.pv').on('input.pv', function(){
      const pid = $row.find(`.prop_pid_${idx}`).val();
      const normalized = normalizeExternal(pid || '', this.value || '');
      if (normalized !== this.value) this.value = normalized;
      // store in hidden and mark picked when non-empty
      $row.find(`.ext_val_${idx}`).val(normalized);
      $(this).toggleClass('pv-picked', !!normalized);
    });
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
        $.getJSON('query.php', { pv: endpoint, q, lang: LANG })
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

    // Property search (datatype comes from server)
    bindSuggest($pIn, $pList, 'propsearch', function(p){
      $pIn.val(p.label || p.id).addClass('pv-picked');
      $row.find(`.prop_pid_${idx}`).val(p.id);
      $pMeta.text('');

      const isExt = p.datatype === 'external-id';
      toggleValueUI($row, idx, isExt, false);

      const advance = !$pIn.data('noAdvance');   // ← only jump on real user pick
      if (advance) {
        if (isExt) $row.find(`#pv_value_ext_input_${idx}`).trigger('focus');
        else $qIn.trigger('focus');
      }
      $pIn.data('noAdvance', false);            // reset for subsequent user picks
    });



    // Q-item search
    bindSuggest($qIn, $qList, 'itemsearch', function(q){
      const qid = q.id || q.qid || q.qitem || q.value || q.q;
      $qIn.val(q.label || qid).addClass('pv-picked').data('pickedId', qid);
      $row.find(`input[name="pv[${idx}][v]"]`).val(qid);
      $qMeta.text('');
    });
    // Allow raw QID typed; mark picked if it matches Q\d+
    $qIn.off('change.pv').on('change.pv', function(){
      const raw = $(this).val().trim();
      const ok = /^Q\d+$/.test(raw);
      $row.find(`.value_qid_${idx}`).val(ok ? raw : '');
      $(this).toggleClass('pv-picked', ok);
      $qMeta.text('');
    });

    // External input: normalize + store in hidden
    wireImdbAndExternalTrim($row, idx);
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

    // label + hidden PID
    $pIn.val(meta.label || meta.id).addClass('pv-picked').data('noAdvance', true);
    $row.find(`.prop_pid_${idx}`).val(meta.id);

    // flip value UI (no autofocus during prefill)
    const isExt = meta.datatype === 'external-id';
    toggleValueUI($row, idx, isExt, true);

    // hard-close any stale suggestions
    $pList.hide().empty();
    $pIn.attr('aria-expanded','false');
  }

  // 1) Apply to the first existing row
  applyOne($listEl.find('.pv-row').eq(0), 0, props[0]);

  // 2) Create + wire + apply the rest, as you go
  for (let i = 1; i < props.length; i++){
    const idx  = $listEl.find('.pv-row').length;
    const $row = $template.clone(false, false);
    renumberRow($row, idx);
    $listEl.append($row);
    wireRow($row, idx);
    applyOne($row, idx, props[i]);    // ← apply NOW, not later
  }

  $('#fullname').focus();
}


// Read current PIDs in order
function currentPIDs(){
  const out = [];
  $('#pv_list .pv-row').each(function(i, row){
    const pid = $(row).find(`.prop_pid_${i}`).val();
    if (pid && /^P\d+$/i.test(pid)) out.push(pid.toUpperCase());
  });
  // de-dupe, keep order
  return out.filter((v,i,a)=>a.indexOf(v)===i);
}

// Keep PIDs in URL (stickiness source)
function syncURLtoCurrentPIDs(){
  const pids = currentPIDs();
  const url  = new URL(location.href);
  if (pids.length) url.searchParams.set('property', pids.join('|'));
  else url.searchParams.delete('property');
  history.replaceState(null, '', url.toString());
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





});
