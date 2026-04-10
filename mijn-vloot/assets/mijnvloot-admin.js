document.addEventListener('DOMContentLoaded', function () {
    const addButton = document.getElementById('mijnvloot_add_images');
    const galleryInput = document.getElementById('mijnvloot_gallery_ids');
    const highlightInput = document.getElementById('mijnvloot_highlight_id');
    const preview = document.getElementById('mijnvloot_gallery_preview');

    if (!addButton || !galleryInput || !highlightInput || !preview) {
        return;
    }

    if (typeof wp === 'undefined' || !wp.media) {
        console.warn('WordPress media library is niet beschikbaar.');
        return;
    }

    let frame = null;

    function getThumbnailUrl(attachment) {
        if (
            attachment.sizes &&
            attachment.sizes.thumbnail &&
            attachment.sizes.thumbnail.url
        ) {
            return attachment.sizes.thumbnail.url;
        }

        if (
            attachment.sizes &&
            attachment.sizes.medium &&
            attachment.sizes.medium.url
        ) {
            return attachment.sizes.medium.url;
        }

        return attachment.url || '';
    }

    function renderPreview(attachments, activeHighlightId) {
        preview.innerHTML = '';

        attachments.forEach(function (attachment, index) {
            const imageUrl = getThumbnailUrl(attachment);
            if (!imageUrl) return;

            const thumb = document.createElement('div');
            thumb.className = 'mijnvloot-thumb';
            thumb.setAttribute('data-id', attachment.id);

            const shouldHighlight =
                String(attachment.id) === String(activeHighlightId) ||
                (!activeHighlightId && index === 0);

            if (shouldHighlight) {
                thumb.classList.add('mijnvloot-highlight');
            }

            thumb.innerHTML = '<img src="' + imageUrl + '" alt="">';
            preview.appendChild(thumb);
        });
    }

    function setHighlight(id) {
        highlightInput.value = id;

        const thumbs = preview.querySelectorAll('.mijnvloot-thumb');
        thumbs.forEach(function (thumb) {
            thumb.classList.remove('mijnvloot-highlight');

            if (String(thumb.getAttribute('data-id')) === String(id)) {
                thumb.classList.add('mijnvloot-highlight');
            }
        });
    }

    addButton.addEventListener('click', function (event) {
        event.preventDefault();

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: 'Kies foto’s voor deze kayak',
            button: {
                text: 'Gebruik deze foto’s'
            },
            multiple: true,
            library: {
                type: 'image'
            }
        });

        frame.on('select', function () {
            const selection = frame.state().get('selection').toJSON();

            if (!selection.length) {
                return;
            }

            const ids = selection.map(function (item) {
                return item.id;
            });

            galleryInput.value = ids.join(',');

            let currentHighlight = highlightInput.value;

            if (!currentHighlight || ids.indexOf(parseInt(currentHighlight, 10)) === -1) {
                currentHighlight = ids[0];
                highlightInput.value = currentHighlight;
            }

            renderPreview(selection, currentHighlight);
        });

        frame.open();
    });

    preview.addEventListener('click', function (event) {
        const thumb = event.target.closest('.mijnvloot-thumb');
        if (!thumb) return;

        const id = thumb.getAttribute('data-id');
        if (!id) return;

        setHighlight(id);
    });
});
