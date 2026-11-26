(() => {
  const editor = document.querySelector('.ts-editor');
  if (!editor) return;
  const form = editor.querySelector('form');
  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', event => {
    if (dirty) { event.preventDefault(); event.returnValue = ''; }
  });
  editor.querySelectorAll('.ts-media').forEach(group => {
    const input = group.previousElementSibling;
    const kind = group.dataset.kind;
    const multiple = kind === 'gallery';
    const preview = group.querySelector('.ts-selected');
    const clear = group.querySelector('.ts-clear');
    const choose = group.querySelector('.ts-pick');
    let frame;
    choose.addEventListener('click', () => {
      if (!frame) {
        frame = wp.media({ title: multiple ? 'Galeria Luna · selectează în ordinea dorită' : 'Alege un material public',
          button: { text: 'Folosește selecția' }, multiple, library: { type: kind === 'video' ? 'video' : 'image' } });
        frame.on('open', () => {
          const selected = frame.state().get('selection');
          selected.reset();
          input.value.split(',').filter(Boolean).forEach(id => {
            const item = wp.media.attachment(Number(id));
            item.fetch(); selected.add(item);
          });
        });
        frame.on('select', () => {
          const items = frame.state().get('selection').toJSON();
          preview.replaceChildren();
          items.forEach(item => {
            if (kind === 'video') preview.append(document.createTextNode(item.title || item.filename));
            else {
              const img = document.createElement('img');
              img.src = item.sizes?.thumbnail?.url || item.url;
              img.alt = item.alt || item.title || '';
              preview.append(img);
            }
          });
          input.value = items.map(item => item.id).join(',');
          clear.hidden = !input.value;
          dirty = true;
        });
      }
      frame.open();
    });
    clear.addEventListener('click', () => {
      input.value = ''; preview.replaceChildren(); clear.hidden = true; dirty = true; choose.focus();
    });
  });
})();
