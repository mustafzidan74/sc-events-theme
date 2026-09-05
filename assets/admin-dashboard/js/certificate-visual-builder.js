/**
 * Visual Certificate Builder JavaScript
 * Drag & Drop Certificate Designer
 *
 * @package sc_events
 */

(function($) {
    'use strict';

    // ================================
    // State Management
    // ================================
    const state = {
        elements: [],
        selectedElement: null,
        isDragging: false,
        isResizing: false,
        dragStartPos: { x: 0, y: 0 },
        elementStartPos: { x: 0, y: 0 },
        elementStartSize: { width: 0, height: 0 },
        resizeHandle: null,
        zoom: 0.75,
        canvasSize: { width: 1123, height: 794 }, // A4 Landscape at 96 DPI (approx)
        templateId: null,
        backgroundImage: null,
        hasUnsavedChanges: false
    };

    // Paper sizes in pixels (at 96 DPI)
    const paperSizes = {
        'A4': { landscape: { width: 1123, height: 794 }, portrait: { width: 794, height: 1123 } },
        'Letter': { landscape: { width: 1056, height: 816 }, portrait: { width: 816, height: 1056 } },
        'A3': { landscape: { width: 1587, height: 1123 }, portrait: { width: 1123, height: 1587 } }
    };

    // Element ID counter
    let elementIdCounter = 1;

    // ================================
    // Initialization
    // ================================
    $(document).ready(function() {
        initializeBuilder();
        setupEventListeners();
        loadExistingTemplate();
    });

    function initializeBuilder() {
        // Set initial zoom
        updateZoom(state.zoom);

        // Initialize from URL param if present
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('template_id')) {
            state.templateId = parseInt(urlParams.get('template_id'));
        }
    }

    function loadExistingTemplate() {
        if (window.existingTemplate && window.existingTemplate !== 'null') {
            const template = window.existingTemplate;

            state.templateId = template.id;
            $('#template-name').val(template.name);
            $('#save-template-name').val(template.name);
            $('#save-template-description').val(template.description || '');

            // Set paper size and orientation
            if (template.paper_size) {
                $('#paper-size').val(template.paper_size);
            }
            if (template.orientation) {
                $('#orientation').val(template.orientation);
                updateCanvasOrientation();
            }

            // Load background image
            if (template.background_image_url) {
                setBackgroundImage(template.background_image_url);
            }

            // Load elements
            if (template.elements_config && template.elements_config.elements) {
                template.elements_config.elements.forEach(function(elemConfig) {
                    createElementFromConfig(elemConfig);
                });
            }
        }
    }

    // ================================
    // Event Listeners Setup
    // ================================
    function setupEventListeners() {
        // Palette drag start
        $('.widget-item').on('dragstart', handlePaletteDragStart);
        $('.widget-item').on('dragend', handlePaletteDragEnd);

        // Canvas drop zone
        const canvas = document.getElementById('certificate-canvas');
        canvas.addEventListener('dragover', handleCanvasDragOver);
        canvas.addEventListener('dragleave', handleCanvasDragLeave);
        canvas.addEventListener('drop', handleCanvasDrop);

        // Canvas click (deselect)
        $('#certificate-canvas').on('click', function(e) {
            if (e.target === this || e.target.id === 'elements-layer' || e.target.id === 'background-layer') {
                deselectAllElements();
            }
        });

        // Keyboard shortcuts
        $(document).on('keydown', handleKeyDown);

        // Zoom control
        $('#canvas-zoom').on('change', function() {
            updateZoom(parseFloat($(this).val()));
        });

        // Paper size change
        $('#paper-size').on('change', updateCanvasOrientation);
        $('#orientation').on('change', updateCanvasOrientation);

        // Background upload
        $('#upload-background-btn, #upload-bg-btn').on('click', function() {
            $('#background-file-input').click();
        });

        $('#background-file-input').on('change', handleBackgroundUpload);

        $('#remove-bg-btn').on('click', function() {
            removeBackgroundImage();
        });

        // Element image upload
        $('#upload-element-image-btn').on('click', function() {
            $('#element-image-file-input').click();
        });

        $('#element-image-file-input').on('change', handleElementImageUpload);

        // Properties panel changes
        setupPropertiesListeners();

        // Save button
        $('#save-visual-template-btn').on('click', openSaveModal);
        $('#confirm-save-btn').on('click', saveTemplate);

        // Preview button
        $('#preview-certificate-btn').on('click', generatePreview);

        // Template selection
        $('#template-select').on('change', function() {
            const templateId = $(this).val();
            if (templateId) {
                window.location.href = window.location.pathname + '?template_id=' + templateId;
            } else {
                window.location.href = window.location.pathname;
            }
        });

        // Delete element button
        $('#delete-element-btn').on('click', function() {
            if (state.selectedElement) {
                deleteElement(state.selectedElement);
            }
        });

        // Warn on unsaved changes
        $(window).on('beforeunload', function() {
            if (state.hasUnsavedChanges) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    }

    function setupPropertiesListeners() {
        // Position changes
        $('#prop-x, #prop-y').on('change', function() {
            if (!state.selectedElement) return;
            const elem = getElementById(state.selectedElement);
            if (elem) {
                elem.x = parseInt($('#prop-x').val()) || 0;
                elem.y = parseInt($('#prop-y').val()) || 0;
                updateElementPosition(state.selectedElement);
                markUnsaved();
            }
        });

        // Size changes
        $('#prop-width, #prop-height').on('change', function() {
            if (!state.selectedElement) return;
            const elem = getElementById(state.selectedElement);
            if (elem) {
                elem.width = parseInt($('#prop-width').val()) || 50;
                elem.height = parseInt($('#prop-height').val()) || 20;
                updateElementSize(state.selectedElement);
                markUnsaved();
            }
        });

        // Text properties
        $('#prop-font-size').on('change', function() {
            updateElementStyle('fontSize', parseInt($(this).val()) + 'px');
        });

        $('#prop-font-family').on('change', function() {
            updateElementStyle('fontFamily', $(this).val());
        });

        $('#prop-font-weight').on('change', function() {
            updateElementStyle('fontWeight', $(this).val());
        });

        $('#prop-color').on('change', function() {
            updateElementStyle('color', $(this).val());
        });

        // Text align buttons
        $('.text-align-btn').on('click', function() {
            $('.text-align-btn').removeClass('active');
            $(this).addClass('active');
            updateElementStyle('textAlign', $(this).data('align'));
        });

        // Shape properties
        $('#prop-bg-color').on('change', function() {
            updateElementStyle('backgroundColor', $(this).val());
        });

        // Content changes
        $('#prop-content').on('input', function() {
            if (!state.selectedElement) return;
            const elem = getElementById(state.selectedElement);
            if (elem) {
                elem.content = $(this).val();
                updateElementContent(state.selectedElement);
                markUnsaved();
            }
        });
    }

    // ================================
    // Drag & Drop from Palette
    // ================================
    function handlePaletteDragStart(e) {
        const type = $(this).data('type');
        e.originalEvent.dataTransfer.setData('text/plain', type);
        e.originalEvent.dataTransfer.effectAllowed = 'copy';
        $(this).addClass('dragging');

        // Create custom drag image
        const ghost = document.createElement('div');
        ghost.className = 'drag-ghost';
        ghost.innerHTML = '<i class="fa ' + window.elementTypes[type].icon + '"></i> ' + window.elementTypes[type].label;
        document.body.appendChild(ghost);
        e.originalEvent.dataTransfer.setDragImage(ghost, 0, 0);
        setTimeout(() => ghost.remove(), 0);
    }

    function handlePaletteDragEnd(e) {
        $(this).removeClass('dragging');
    }

    function handleCanvasDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        $(this).addClass('drag-over');
    }

    function handleCanvasDragLeave(e) {
        $(this).removeClass('drag-over');
    }

    function handleCanvasDrop(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');

        const type = e.dataTransfer.getData('text/plain');
        if (!type || !window.elementTypes[type]) return;

        const canvas = document.getElementById('certificate-canvas');
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / state.zoom;
        const y = (e.clientY - rect.top) / state.zoom;

        createElement(type, x, y);
    }

    // ================================
    // Element Creation
    // ================================
    function createElement(type, x, y) {
        const config = window.elementTypes[type];
        const id = 'elem_' + (elementIdCounter++);

        const element = {
            id: id,
            type: type,
            x: Math.round(x - 50),
            y: Math.round(y - 15),
            width: config.default_style.width || 200,
            height: config.default_style.height || 40,
            fontSize: config.default_style.fontSize || 16,
            fontFamily: config.default_style.fontFamily || 'Arial, sans-serif',
            fontWeight: config.default_style.fontWeight || 'normal',
            color: config.default_style.color || '#000000',
            textAlign: config.default_style.textAlign || 'center',
            backgroundColor: config.default_style.backgroundColor || 'transparent',
            content: config.editable_content ? 'Custom Text' : '',
            imageUrl: ''
        };

        // Special sizes for certain elements
        if (type === 'qr_code') {
            element.width = 100;
            element.height = 100;
        } else if (type === 'line') {
            element.width = 200;
            element.height = 3;
        }

        state.elements.push(element);
        renderElement(element);
        selectElement(id);
        markUnsaved();
    }

    function createElementFromConfig(config) {
        const id = config.id || 'elem_' + (elementIdCounter++);
        elementIdCounter = Math.max(elementIdCounter, parseInt(id.replace('elem_', '')) + 1);

        const element = {
            id: id,
            type: config.type,
            x: config.x || 0,
            y: config.y || 0,
            width: config.width || 200,
            height: config.height || 40,
            fontSize: config.fontSize || 16,
            fontFamily: config.fontFamily || 'Arial, sans-serif',
            fontWeight: config.fontWeight || 'normal',
            color: config.color || '#000000',
            textAlign: config.textAlign || 'center',
            backgroundColor: config.backgroundColor || 'transparent',
            content: config.content || '',
            imageUrl: config.imageUrl || ''
        };

        state.elements.push(element);
        renderElement(element);
    }

    function renderElement(element) {
        const config = window.elementTypes[element.type];
        const $container = $('#elements-layer');

        let contentHtml = '';
        let extraClasses = '';

        if (config.is_image && element.type === 'qr_code') {
            extraClasses = 'qr-element';
            contentHtml = '<div class="qr-placeholder"><i class="fa fa-qrcode"></i><span>QR Code</span></div>';
        } else if (config.is_image && element.type === 'custom_image') {
            extraClasses = 'image-element';
            if (element.imageUrl) {
                contentHtml = '<img src="' + escapeHtml(element.imageUrl) + '" alt="Custom Image">';
            } else {
                contentHtml = '<div class="text-center text-muted"><i class="fa fa-image"></i><br><small>Click to upload</small></div>';
            }
        } else if (config.is_shape) {
            extraClasses = 'shape-element line-element';
            contentHtml = '';
        } else {
            extraClasses = 'text-element';
            contentHtml = element.content || config.placeholder || config.label;
        }

        const $elem = $(`
            <div class="canvas-element ${extraClasses}" data-id="${element.id}" data-type="${element.type}">
                <span class="element-label">${config.label}</span>
                <div class="element-content">${contentHtml}</div>
                <div class="resize-handle nw"></div>
                <div class="resize-handle ne"></div>
                <div class="resize-handle sw"></div>
                <div class="resize-handle se"></div>
                <div class="resize-handle n"></div>
                <div class="resize-handle s"></div>
                <div class="resize-handle e"></div>
                <div class="resize-handle w"></div>
            </div>
        `);

        $container.append($elem);
        updateElementPosition(element.id);
        updateElementSize(element.id);
        applyElementStyles(element.id);

        // Element event listeners
        $elem.on('mousedown', function(e) {
            if ($(e.target).hasClass('resize-handle')) {
                startResize(e, element.id, $(e.target));
            } else {
                selectElement(element.id);
                startDrag(e, element.id);
            }
        });

        $elem.on('click', function(e) {
            e.stopPropagation();
            selectElement(element.id);
        });
    }

    // ================================
    // Element Selection
    // ================================
    function selectElement(id) {
        deselectAllElements();
        state.selectedElement = id;

        const $elem = $(`.canvas-element[data-id="${id}"]`);
        $elem.addClass('selected');

        const elem = getElementById(id);
        if (elem) {
            showPropertiesPanel(elem);
        }
    }

    function deselectAllElements() {
        state.selectedElement = null;
        $('.canvas-element').removeClass('selected');
        $('#element-properties-panel').hide();
    }

    function showPropertiesPanel(element) {
        const config = window.elementTypes[element.type];
        const $panel = $('#element-properties-panel');

        $panel.show();
        $('#prop-element-type').val(config.label);

        // Position
        $('#prop-x').val(element.x);
        $('#prop-y').val(element.y);

        // Size
        $('#prop-width').val(element.width);
        $('#prop-height').val(element.height);

        // Show/hide relevant sections
        if (config.is_shape) {
            $('#text-properties').hide();
            $('#shape-properties').show();
            $('#prop-bg-color').val(element.backgroundColor || '#333333');
        } else if (config.is_image) {
            $('#text-properties').hide();
            $('#shape-properties').hide();

            if (config.uploadable) {
                $('#prop-image-group').show();
                $('#prop-image-url').val(element.imageUrl || '');
            } else {
                $('#prop-image-group').hide();
            }
        } else {
            $('#text-properties').show();
            $('#shape-properties').hide();
            $('#prop-image-group').hide();

            // Text properties
            $('#prop-font-size').val(parseInt(element.fontSize) || 16);
            $('#prop-font-family').val(element.fontFamily || 'Arial, sans-serif');
            $('#prop-font-weight').val(element.fontWeight || 'normal');
            $('#prop-color').val(element.color || '#000000');

            // Text align
            $('.text-align-btn').removeClass('active');
            $(`.text-align-btn[data-align="${element.textAlign || 'center'}"]`).addClass('active');
        }

        // Content field
        if (config.editable_content) {
            $('#prop-content-group').show();
            $('#prop-content').val(element.content || '');
        } else {
            $('#prop-content-group').hide();
        }
    }

    // ================================
    // Element Dragging
    // ================================
    function startDrag(e, id) {
        if (e.button !== 0) return; // Only left click

        state.isDragging = true;
        state.dragStartPos = { x: e.clientX, y: e.clientY };

        const elem = getElementById(id);
        state.elementStartPos = { x: elem.x, y: elem.y };

        $(`.canvas-element[data-id="${id}"]`).addClass('dragging');

        $(document).on('mousemove.drag', function(e) {
            handleDrag(e, id);
        });

        $(document).on('mouseup.drag', function() {
            endDrag(id);
        });
    }

    function handleDrag(e, id) {
        if (!state.isDragging) return;

        const dx = (e.clientX - state.dragStartPos.x) / state.zoom;
        const dy = (e.clientY - state.dragStartPos.y) / state.zoom;

        const elem = getElementById(id);
        elem.x = Math.max(0, Math.min(state.canvasSize.width - elem.width, Math.round(state.elementStartPos.x + dx)));
        elem.y = Math.max(0, Math.min(state.canvasSize.height - elem.height, Math.round(state.elementStartPos.y + dy)));

        updateElementPosition(id);

        // Update properties panel
        $('#prop-x').val(elem.x);
        $('#prop-y').val(elem.y);
    }

    function endDrag(id) {
        state.isDragging = false;
        $(`.canvas-element[data-id="${id}"]`).removeClass('dragging');
        $(document).off('.drag');
        markUnsaved();
    }

    // ================================
    // Element Resizing
    // ================================
    function startResize(e, id, $handle) {
        e.stopPropagation();
        e.preventDefault();

        state.isResizing = true;
        state.resizeHandle = $handle.attr('class').split(' ').find(c => ['nw', 'ne', 'sw', 'se', 'n', 's', 'e', 'w'].includes(c));
        state.dragStartPos = { x: e.clientX, y: e.clientY };

        const elem = getElementById(id);
        state.elementStartPos = { x: elem.x, y: elem.y };
        state.elementStartSize = { width: elem.width, height: elem.height };

        $(document).on('mousemove.resize', function(e) {
            handleResize(e, id);
        });

        $(document).on('mouseup.resize', function() {
            endResize();
        });
    }

    function handleResize(e, id) {
        if (!state.isResizing) return;

        const dx = (e.clientX - state.dragStartPos.x) / state.zoom;
        const dy = (e.clientY - state.dragStartPos.y) / state.zoom;
        const elem = getElementById(id);
        const handle = state.resizeHandle;

        let newX = state.elementStartPos.x;
        let newY = state.elementStartPos.y;
        let newWidth = state.elementStartSize.width;
        let newHeight = state.elementStartSize.height;

        // Handle resize based on direction
        if (handle.includes('e')) {
            newWidth = Math.max(30, state.elementStartSize.width + dx);
        }
        if (handle.includes('w')) {
            newWidth = Math.max(30, state.elementStartSize.width - dx);
            newX = state.elementStartPos.x + (state.elementStartSize.width - newWidth);
        }
        if (handle.includes('s')) {
            newHeight = Math.max(15, state.elementStartSize.height + dy);
        }
        if (handle.includes('n')) {
            newHeight = Math.max(15, state.elementStartSize.height - dy);
            newY = state.elementStartPos.y + (state.elementStartSize.height - newHeight);
        }

        elem.x = Math.round(newX);
        elem.y = Math.round(newY);
        elem.width = Math.round(newWidth);
        elem.height = Math.round(newHeight);

        updateElementPosition(id);
        updateElementSize(id);

        // Update properties panel
        $('#prop-x').val(elem.x);
        $('#prop-y').val(elem.y);
        $('#prop-width').val(elem.width);
        $('#prop-height').val(elem.height);
    }

    function endResize() {
        state.isResizing = false;
        state.resizeHandle = null;
        $(document).off('.resize');
        markUnsaved();
    }

    // ================================
    // Element Updates
    // ================================
    function updateElementPosition(id) {
        const elem = getElementById(id);
        const $elem = $(`.canvas-element[data-id="${id}"]`);
        $elem.css({
            left: elem.x + 'px',
            top: elem.y + 'px'
        });
    }

    function updateElementSize(id) {
        const elem = getElementById(id);
        const $elem = $(`.canvas-element[data-id="${id}"]`);
        $elem.css({
            width: elem.width + 'px',
            height: elem.height + 'px'
        });
    }

    function applyElementStyles(id) {
        const elem = getElementById(id);
        const $content = $(`.canvas-element[data-id="${id}"] .element-content`);
        const config = window.elementTypes[elem.type];

        if (config.is_shape) {
            $content.css({
                backgroundColor: elem.backgroundColor || '#333333'
            });
        } else if (!config.is_image) {
            $content.css({
                fontSize: elem.fontSize + 'px',
                fontFamily: elem.fontFamily,
                fontWeight: elem.fontWeight,
                color: elem.color,
                textAlign: elem.textAlign,
                justifyContent: elem.textAlign === 'left' ? 'flex-start' : (elem.textAlign === 'right' ? 'flex-end' : 'center')
            });
        }
    }

    function updateElementStyle(property, value) {
        if (!state.selectedElement) return;

        const elem = getElementById(state.selectedElement);
        if (!elem) return;

        // Map CSS property to element property
        const propMap = {
            'fontSize': 'fontSize',
            'fontFamily': 'fontFamily',
            'fontWeight': 'fontWeight',
            'color': 'color',
            'textAlign': 'textAlign',
            'backgroundColor': 'backgroundColor'
        };

        const elemProp = propMap[property];
        if (elemProp) {
            elem[elemProp] = property === 'fontSize' ? parseInt(value) : value;
        }

        applyElementStyles(state.selectedElement);
        markUnsaved();
    }

    function updateElementContent(id) {
        const elem = getElementById(id);
        const $content = $(`.canvas-element[data-id="${id}"] .element-content`);
        $content.text(elem.content || 'Custom Text');
    }

    function deleteElement(id) {
        const index = state.elements.findIndex(e => e.id === id);
        if (index !== -1) {
            state.elements.splice(index, 1);
            $(`.canvas-element[data-id="${id}"]`).remove();
            deselectAllElements();
            markUnsaved();
        }
    }

    // ================================
    // Canvas Operations
    // ================================
    function updateZoom(zoom) {
        state.zoom = zoom;
        $('#canvas-wrapper').css('transform', `scale(${zoom})`);
    }

    function updateCanvasOrientation() {
        const paperSize = $('#paper-size').val();
        const orientation = $('#orientation').val();

        const sizes = paperSizes[paperSize] || paperSizes['A4'];
        const size = sizes[orientation];

        state.canvasSize = { width: size.width, height: size.height };

        $('#certificate-canvas').css({
            width: size.width + 'px',
            height: size.height + 'px'
        });

        markUnsaved();
    }

    function setBackgroundImage(url) {
        state.backgroundImage = url;
        $('#background-image').attr('src', url).show();
        $('#background-placeholder').hide();
        $('#background-url').val(url);
        markUnsaved();
    }

    function removeBackgroundImage() {
        state.backgroundImage = null;
        $('#background-image').attr('src', '').hide();
        $('#background-placeholder').show();
        $('#background-url').val('');
        markUnsaved();
    }

    function handleBackgroundUpload(e) {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('action', 'sc_upload_certificate_background');
        formData.append('nonce', scDashboard.nonce);
        formData.append('file', file);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    setBackgroundImage(response.data.url);
                    toastr.success('Background uploaded successfully');
                } else {
                    toastr.error(response.data.message || 'Upload failed');
                }
            },
            error: function() {
                toastr.error('Upload failed');
            }
        });
    }

    function handleElementImageUpload(e) {
        const file = e.target.files[0];
        if (!file || !state.selectedElement) return;

        const formData = new FormData();
        formData.append('action', 'sc_upload_certificate_element_image');
        formData.append('nonce', scDashboard.nonce);
        formData.append('file', file);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const elem = getElementById(state.selectedElement);
                    elem.imageUrl = response.data.url;
                    $('#prop-image-url').val(response.data.url);

                    const $content = $(`.canvas-element[data-id="${state.selectedElement}"] .element-content`);
                    $content.html('<img src="' + escapeHtml(response.data.url) + '" alt="Custom Image">');

                    markUnsaved();
                    toastr.success('Image uploaded successfully');
                } else {
                    toastr.error(response.data.message || 'Upload failed');
                }
            },
            error: function() {
                toastr.error('Upload failed');
            }
        });
    }

    // ================================
    // Keyboard Shortcuts
    // ================================
    function handleKeyDown(e) {
        // Delete key
        if (e.key === 'Delete' || e.key === 'Backspace') {
            if (state.selectedElement && !$(e.target).is('input, textarea')) {
                e.preventDefault();
                deleteElement(state.selectedElement);
            }
        }

        // Arrow keys for nudging
        if (state.selectedElement && !$(e.target).is('input, textarea')) {
            const nudge = e.shiftKey ? 10 : 1;
            const elem = getElementById(state.selectedElement);

            switch (e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    elem.x = Math.max(0, elem.x - nudge);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    elem.x = Math.min(state.canvasSize.width - elem.width, elem.x + nudge);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    elem.y = Math.max(0, elem.y - nudge);
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    elem.y = Math.min(state.canvasSize.height - elem.height, elem.y + nudge);
                    break;
            }

            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                updateElementPosition(state.selectedElement);
                $('#prop-x').val(elem.x);
                $('#prop-y').val(elem.y);
                markUnsaved();
            }
        }

        // Ctrl+S to save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            openSaveModal();
        }
    }

    // ================================
    // Save & Load
    // ================================
    function openSaveModal() {
        const name = $('#template-name').val() || 'Untitled Template';
        $('#save-template-name').val(name);
        $('#saveModal').modal('show');
    }

    function saveTemplate() {
        const name = $('#save-template-name').val().trim();
        if (!name) {
            toastr.error('Please enter a template name');
            return;
        }

        const elementsConfig = {
            background_image: state.backgroundImage,
            canvas_width: state.canvasSize.width,
            canvas_height: state.canvasSize.height,
            elements: state.elements.map(e => ({
                id: e.id,
                type: e.type,
                x: e.x,
                y: e.y,
                width: e.width,
                height: e.height,
                fontSize: e.fontSize,
                fontFamily: e.fontFamily,
                fontWeight: e.fontWeight,
                color: e.color,
                textAlign: e.textAlign,
                backgroundColor: e.backgroundColor,
                content: e.content,
                imageUrl: e.imageUrl
            }))
        };

        const data = {
            action: 'sc_save_visual_certificate_template',
            nonce: scDashboard.nonce,
            template_id: state.templateId || 0,
            name: name,
            description: $('#save-template-description').val(),
            paper_size: $('#paper-size').val(),
            orientation: $('#orientation').val(),
            background_image: state.backgroundImage,
            elements_config: JSON.stringify(elementsConfig),
            is_active: $('#save-is-active').is(':checked') ? 1 : 0,
            is_default: $('#save-is-default').is(':checked') ? 1 : 0
        };

        $('#confirm-save-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                $('#confirm-save-btn').prop('disabled', false).html('<i class="fa fa-save"></i> Save Template');

                if (response.success) {
                    state.templateId = response.data.template_id;
                    state.hasUnsavedChanges = false;
                    $('#saveModal').modal('hide');
                    toastr.success('Template saved successfully!');

                    // Update URL
                    const newUrl = window.location.pathname + '?template_id=' + state.templateId;
                    window.history.replaceState(null, '', newUrl);
                } else {
                    toastr.error(response.data.message || 'Failed to save template');
                }
            },
            error: function() {
                $('#confirm-save-btn').prop('disabled', false).html('<i class="fa fa-save"></i> Save Template');
                toastr.error('Connection error. Please try again.');
            }
        });
    }

    // ================================
    // Preview
    // ================================
    function generatePreview() {
        const elementsConfig = {
            background_image: state.backgroundImage,
            canvas_width: state.canvasSize.width,
            canvas_height: state.canvasSize.height,
            elements: state.elements
        };

        $('#preview-content').html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x"></i><p class="mt-3">Generating preview...</p></div>');
        $('#previewModal').modal('show');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_preview_visual_certificate',
                nonce: scDashboard.nonce,
                elements_config: JSON.stringify(elementsConfig),
                paper_size: $('#paper-size').val(),
                orientation: $('#orientation').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#preview-content').html(response.data.html);
                    $('#preview-content').css({
                        width: (state.canvasSize.width * 0.6) + 'px',
                        height: (state.canvasSize.height * 0.6) + 'px'
                    });
                } else {
                    $('#preview-content').html('<div class="alert alert-danger">Preview generation failed</div>');
                }
            },
            error: function() {
                $('#preview-content').html('<div class="alert alert-danger">Connection error</div>');
            }
        });
    }

    // ================================
    // Utility Functions
    // ================================
    function getElementById(id) {
        return state.elements.find(e => e.id === id);
    }

    function markUnsaved() {
        state.hasUnsavedChanges = true;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
