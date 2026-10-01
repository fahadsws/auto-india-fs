/* Admin UI behaviour: DataTables (server-side), searchable selects, date pickers, TinyMCE, confirm dialogs. */
(function ($) {
  'use strict';
  const A = window.ADMIN || {};
  if (window.toastr) toastr.options = { closeButton: true, progressBar: true, newestOnTop: true, preventDuplicates: true, positionClass: 'toast-top-right', timeOut: 4500, extendedTimeOut: 2000, showDuration: 200, hideDuration: 300 };

  /* ---------- searchable dropdowns (Select2) ---------- */
  function initSelects(scope) {
    $(scope || document).find('select:not([data-plain]):not(.select2-hidden-accessible):not(.dataTables_length select)').each(function () {
      const $s = $(this);
      const first = $s.find('option:first');
      const emptyFirst = first.length && first.val() === '';
      $s.select2({
        width: '100%',
        dropdownParent: $s.closest('.modal').length ? $s.closest('.modal') : $(document.body),
        placeholder: $s.data('placeholder') || (emptyFirst ? first.text() : undefined),
        allowClear: emptyFirst && !$s.prop('multiple') ? false : !!$s.prop('multiple'),
        minimumResultsForSearch: $s.data('search') === 'off' ? Infinity : 0,
        closeOnSelect: !$s.prop('multiple'),
      });
    });
  }

  /* ---------- date pickers (Flatpickr) ---------- */
  function initDates(scope) {
    $(scope || document).find('input[type=date], input[type=datetime-local], input.flatpickr-range').each(function () {
      if (this._flatpickr) return;
      const el = this, range = el.classList.contains('flatpickr-range'), time = el.type === 'datetime-local';
      el.type = 'text'; if (range) el.classList.add('form-control');
      flatpickr(el, {
        mode: range ? 'range' : 'single',
        enableTime: time, time_24hr: false,
        dateFormat: range ? 'Y-m-d' : (time ? 'Y-m-d\\TH:i' : 'Y-m-d'),
        altInput: !range, altFormat: time ? 'd M Y, h:i K' : 'd M Y', altInputClass: 'form-control',
        allowInput: false, disableMobile: true, locale: { rangeSeparator: ' to ' },
        onClose: function () { $(el).trigger('change'); },
      });
    });
  }

  /* ---------- DataTables (+ bulk actions) ---------- */
  function initTables() {
    $('table.dt').each(function () {
      const $t = $(this), wrap = $t.closest('.dt-wrap'), cols = JSON.parse(this.dataset.dtCols || '[]');
      const bulk = this.dataset.bulk === '1', bulkAll = this.dataset.bulkAll === '1';
      const bar = wrap.find('.dt-bulkbar');
      let json = null, allMode = false;

      const columns = cols.map((c, i) => ({ data: i, orderable: !!c.orderable, className: c.class || '' }));
      let order = JSON.parse(this.dataset.dtOrder || '[[0,"desc"]]');
      if (bulk) {
        columns.unshift({ data: null, orderable: false, searchable: false, className: 'dt-check text-center',
          render: (d, t, row, meta) => '<input type="checkbox" class="form-check-input dt-row" value="' + (json && json.ids ? json.ids[meta.row] : '') + '">' });
        order = order.map(o => [o[0] + 1, o[1]]);
        columns.forEach((c, i) => { if (i > 0) c.data = i; });   // data columns keep their index; index 0 is the checkbox
      }
      const filters = () => { const o = {}; wrap.find('.dt-filter').each(function () { if (this.name) o[this.name] = $(this).val(); }); return o; };

      const dt = $t.DataTable({
        processing: true, serverSide: true, pageLength: parseInt(this.dataset.dtLength || 15, 10),
        lengthMenu: [10, 15, 25, 50, 100], order: order,
        ajax: {
          url: this.dataset.dtUrl, type: 'GET',
          data: function (d) {
            Object.assign(d, filters());
            if (bulk && d.order && d.order.length) d.order[0].column = Math.max(0, d.order[0].column - 1); // server knows nothing of the checkbox column
          },
          dataSrc: function (j) { json = j; return j.data; },
          error: function () { console.error('Table failed to load'); },
        },
        columns: columns.map((c, i) => bulk ? (i === 0 ? c : Object.assign({}, c, { data: i - 1 })) : c),
        language: { search: '', searchPlaceholder: 'Search…', lengthMenu: '_MENU_', processing: 'Loading…', emptyTable: 'Nothing found', zeroRecords: 'No matching records', info: 'Showing _START_–_END_ of _TOTAL_' },
        dom: '<"dt-top d-flex flex-wrap justify-content-between align-items-center px-4 py-3 gap-2"<"d-flex gap-2 align-items-center"l><"dt-search"f>>t<"dt-bottom d-flex flex-wrap justify-content-between align-items-center px-4 py-3 gap-2"ip>',
        drawCallback: function () { $('.dataTables_wrapper .pagination').addClass('pagination-sm mb-0'); if (bulk) { wrap.find('.dt-all').prop('checked', false); resetSelection(); } },
      });

      wrap.on('change', '.dt-filter', function () { dt.draw(); });
      wrap.on('click', '.dt-reset', function () {
        wrap.find('.dt-filter').each(function () { if (this._flatpickr) this._flatpickr.clear(); else $(this).val('').trigger('change.select2'); });
        dt.search('').draw();
      });
      wrap.data('dt', dt);
      if (!bulk) return;

      /* ----- selection ----- */
      const rows = () => wrap.find('tbody .dt-row');
      const selected = () => rows().filter(':checked').map(function () { return this.value; }).get();
      function refresh() {
        const n = allMode ? (json ? json.recordsFiltered : 0) : selected().length;
        bar.toggleClass('d-none', n === 0); bar.find('.dt-count').text(n.toLocaleString());
        const pageAll = rows().length && rows().length === selected().length;
        wrap.find('.dt-all').prop('checked', pageAll);
        const sa = bar.find('.dt-selectall').addClass('d-none').empty();
        if (bulkAll && json && pageAll && json.recordsFiltered > rows().length && !allMode)
          sa.removeClass('d-none').html('All ' + rows().length + ' on this page selected. <a href="#" class="dt-pick-all fw-semibold">Select all ' + json.recordsFiltered.toLocaleString() + ' matching the filters</a>');
        if (allMode) sa.removeClass('d-none').html('All ' + json.recordsFiltered.toLocaleString() + ' matching results selected. <a href="#" class="dt-clear-all fw-semibold">Undo</a>');
      }
      function resetSelection() { allMode = false; refresh(); }
      wrap.on('change', '.dt-all', function () { rows().prop('checked', this.checked); allMode = false; refresh(); });
      wrap.on('change', '.dt-row', function () { allMode = false; refresh(); });
      wrap.on('click', 'tbody tr', function (e) { if ($(e.target).closest('a,button,input,form,label,select').length) return; const c = $(this).find('.dt-row'); c.prop('checked', !c.prop('checked')).trigger('change'); });
      bar.on('click', '.dt-pick-all', e => { e.preventDefault(); allMode = true; refresh(); });
      bar.on('click', '.dt-clear-all', e => { e.preventDefault(); allMode = false; refresh(); });
      bar.on('click', '.dt-bulk-clear', () => { rows().prop('checked', false); wrap.find('.dt-all').prop('checked', false); allMode = false; refresh(); });

      /* ----- run an action from the menu (asks for a value first when the action needs one) ----- */
      bar.on('click', '.dt-bulk-item', function (e) {
        e.preventDefault();
        const it = this.dataset, danger = it.danger === '1', options = it.options ? JSON.parse(it.options) : null;
        const n = allMode ? (json ? json.recordsFiltered : 0) : selected().length; if (!n) return;
        const cnt = n.toLocaleString() + ' record' + (n === 1 ? '' : 's');
        confirmDialog({
          title: it.label, danger: danger, icon: danger ? 'ti-alert-triangle' : (options ? 'ti-adjustments' : 'ti-help-circle'),
          html: (it.confirm ? esc(it.confirm) + '<br>' : '') + '<b>' + cnt + '</b> will be affected.', okText: 'Apply to ' + cnt, options: options,
        }).then(res => {
          if (!res.confirmed) return;
          const body = { action: it.key, value: options ? (res.value === '' ? null : res.value) : null };
          if (allMode) { body.all = 1; body.search = dt.search(); Object.assign(body, filters()); } else body.ids = selected();
          bar.find('.dropdown-toggle').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Working…');
          fetch(bar.data('url'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': A.csrf }, body: JSON.stringify(body) })
            .then(r => r.json().then(j => ({ ok: r.ok, j })))
            .then(({ ok, j }) => toast(ok ? 'success' : 'error', j.message || (ok ? 'Done' : 'Something went wrong')))
            .catch(() => toast('error', 'Request failed. Please try again.'))
            .finally(() => { bar.find('.dropdown-toggle').prop('disabled', false).html('<i class="ti ti-list-check me-1"></i>Bulk actions'); allMode = false; dt.draw(false); });
        });
      });
    });
  }

  /* ---------- TinyMCE (GPL, self-hosted; uploads go to disk, never base64) ---------- */
  function upload(file, progress) {
    return new Promise(function (resolve, reject) {
      const fd = new FormData(); fd.append('file', file, file.name || 'upload.png'); fd.append('_token', A.csrf);
      const xhr = new XMLHttpRequest(); xhr.open('POST', A.uploadUrl); xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.onprogress = e => progress && e.lengthComputable && progress(e.loaded / e.total * 100);
      xhr.onload = function () {
        let j = {}; try { j = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status === 200 && j.location) resolve(j.location);
        else reject({ message: (j.message || 'Upload failed') + ' (HTTP ' + xhr.status + ')', remove: true });
      };
      xhr.onerror = () => reject({ message: 'Upload failed: network error', remove: true });
      xhr.send(fd);
    });
  }
  function initEditors() {
    if (!window.tinymce || !$('textarea.editor').length) return;
    tinymce.init({
      selector: 'textarea.editor', license_key: 'gpl', base_url: A.tinymce, suffix: '.min',
      promotion: false, branding: false, height: 520, toolbar_mode: 'wrap', menubar: 'file edit view insert format table tools',
      plugins: 'advlist autolink lists link image media table code fullscreen preview searchreplace wordcount charmap anchor insertdatetime visualblocks autoresize',
      toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist outdent indent | link image media table | blockquote hr | removeformat | code fullscreen',
      block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4',
      autoresize_bottom_margin: 20, min_height: 420, max_height: 900,
      relative_urls: false, remove_script_host: true, convert_urls: true,
      image_caption: true, image_advtab: true, image_description: true, image_dimensions: false,
      automatic_uploads: true, paste_data_images: true, images_reuse_filename: false,
      images_upload_handler: (blob, progress) => upload(blob.blob(), progress),
      file_picker_types: 'image media',
      file_picker_callback: function (cb, value, meta) {
        const input = document.createElement('input'); input.type = 'file'; input.accept = meta.filetype === 'image' ? 'image/*' : 'video/mp4,video/webm';
        input.onchange = function () { if (input.files[0]) upload(input.files[0]).then(url => cb(url)).catch(e => alert(e.message)); };
        input.click();
      },
      content_style: 'body{font-family:Public Sans,system-ui,sans-serif;font-size:16px;line-height:1.7;padding:12px} img{max-width:100%;height:auto}',
      setup: ed => ed.on('change input undo redo', () => ed.save()),
    });
  }

  /* ---------- toasts (Toastr) ---------- */
  function toast(type, message, title) {
    if (window.toastr) toastr[type === 'error' ? 'error' : (type === 'warning' ? 'warning' : (type === 'info' ? 'info' : 'success'))](message, title || '');
  }
  const esc = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  /* ---------- confirmation modal (Bootstrap) - optional select for a value ---------- */
  function confirmDialog(o) {
    return new Promise(function (resolve) {
      const el = document.getElementById('confirmModal'), $el = $(el), ModalCls = (window.bootstrap && window.bootstrap.Modal) || window.Modal, modal = ModalCls.getOrCreateInstance(el);
      let done = false;
      $el.find('.cm-icon').attr('class', 'cm-icon mb-3 ' + (o.danger ? 'text-danger' : 'text-primary')).html('<i class="ti ' + (o.icon || 'ti-help-circle') + '"></i>');
      $el.find('.cm-title').text(o.title || 'Are you sure?');
      $el.find('.cm-text').html(o.html || '');
      const $sel = $el.find('#cmSelect'), $wrap = $el.find('.cm-input');
      if (o.options && o.options.length) {
        $sel.empty(); o.options.forEach(x => $sel.append(new Option(x[1], x[0])));
        if (!$sel.hasClass('select2-hidden-accessible')) { $sel.removeAttr('data-plain'); AdminUI.initSelects(el); }
        $sel.val(o.options[0][0]).trigger('change'); $wrap.removeClass('d-none');
      } else $wrap.addClass('d-none');
      $el.find('.cm-ok').attr('class', 'btn cm-ok ' + (o.danger ? 'btn-danger' : 'btn-primary')).text(o.okText || 'Confirm').off('click').on('click', function () {
        done = true; resolve({ confirmed: true, value: o.options ? $sel.val() : null }); modal.hide();
      });
      $el.off('hidden.bs.modal').on('hidden.bs.modal', function () { if (!done) resolve({ confirmed: false }); });
      $el.off('shown.bs.modal').on('shown.bs.modal', function () { $el.find('.cm-ok').trigger('focus'); });
      modal.show();
    });
  }

  /* ---------- confirm before destructive forms: <form data-confirm="..."> ---------- */
  $(document).on('submit', 'form[data-confirm]', function (e) {
    const f = this; if (f.dataset.ok) return;
    e.preventDefault();
    confirmDialog({ title: 'Are you sure?', danger: true, icon: 'ti-alert-triangle', html: esc(f.dataset.confirm), okText: 'Yes, continue' })
      .then(r => { if (r.confirmed) { f.dataset.ok = 1; f.submit(); } });
  });

  /* ---------- flash messages from the server -> toasts ---------- */
  function showFlash() { (A.flash || []).forEach(m => toast(m.type, m.message)); }
  $(function () { initSelects(); initDates(); initTables(); initEditors(); showFlash(); });
  window.AdminUI = { initSelects: initSelects, initDates: initDates, toast: toast, confirm: confirmDialog };
})(jQuery);
