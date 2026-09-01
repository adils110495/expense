/**
 * Admin panel interactions.
 *
 * Every navigation and every write goes over fetch and swaps only the #page
 * region - links, filter forms, and create/update/delete submissions alike.
 * The CSS is never re-parsed and the sidebar never repaints, so the panel
 * feels instant.
 *
 * Two layers:
 *  1. Delegated handlers bound once to `document` - these survive page swaps.
 *  2. initPage(), re-run after every swap, for anything bound per element.
 *
 * All of it is progressive enhancement: with JS off, every link and form is a
 * plain request that renders the same page.
 *
 * IMPORTANT: requests must NOT send X-Requested-With. Laravel's
 * Request::expectsJson() is true for an XHR with `Accept: *​/*`, which would
 * turn validation redirects into JSON 422 responses. Asking for text/html
 * keeps the normal redirect-with-errors flow intact.
 */
(function () {
    'use strict';

    var body = document.body;
    var PAGE = '#page';
    var cache = new Map();   // url -> html, for hover prefetch
    var inflight = null;

    function request(url, options) {
        var config = options || {};
        config.credentials = 'same-origin';
        config.headers = { 'Accept': 'text/html, application/xhtml+xml' };

        return fetch(url, config).then(function (response) {
            return response.text().then(function (html) {
                return { html: html, url: response.url || url, ok: response.ok };
            });
        });
    }

    /* ================= Delegated handlers (bound once) ================= */

    function closeNav() {
        body.classList.remove('nav-open');
        var toggle = document.querySelector('[data-nav-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-nav-toggle]');
        if (toggle) {
            var open = body.classList.toggle('nav-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }

        if (event.target.closest('[data-nav-close]')) closeNav();
    });

    /* ---------- Modals ---------- */

    function openModal(id, fill) {
        var modal = document.getElementById(id);
        if (!modal) return;

        if (fill) {
            Object.keys(fill).forEach(function (name) {
                var field = modal.querySelector('[name="' + name + '"]');
                if (!field) return;
                if (field.type === 'checkbox') field.checked = fill[name] === '1';
                else field.value = fill[name];
            });
        }

        modal.hidden = false;
        var first = modal.querySelector('input:not([type=hidden]), select, textarea');
        if (first) first.focus();
    }

    function closeModal(modal) {
        if (modal) modal.hidden = true;
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-modal-open]');
        if (opener) {
            event.preventDefault();

            var fill = {};
            Object.keys(opener.dataset).forEach(function (key) {
                if (key.indexOf('field') === 0 && key.length > 5) {
                    var name = key.charAt(5).toLowerCase() + key.slice(6);
                    fill[name.replace(/[A-Z]/g, function (c) { return '_' + c.toLowerCase(); })] = opener.dataset[key];
                }
            });

            var modal = document.getElementById(opener.dataset.modalOpen);
            if (modal && opener.dataset.action) {
                var form = modal.querySelector('form');
                if (form) form.setAttribute('action', opener.dataset.action);
            }
            if (modal && opener.dataset.title) {
                var heading = modal.querySelector('[data-modal-title]');
                if (heading) heading.textContent = opener.dataset.title;
            }
            if (modal && opener.dataset.method) {
                var methodField = modal.querySelector('input[name="_method"]');
                if (methodField) methodField.value = opener.dataset.method;
            }

            openModal(opener.dataset.modalOpen, fill);
            return;
        }

        if (event.target.closest('[data-modal-close]')) {
            closeModal(event.target.closest('.modal'));
            return;
        }

        if (event.target.classList.contains('modal')) closeModal(event.target);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.modal:not([hidden])').forEach(closeModal);
        closeNav();
    });

    /* ---------- Hierarchy tree: expand / collapse ----------
       Delegated, so the tree keeps working after a page swap. Collapsing is a
       class on the node; the branch stays in the DOM, which is what lets
       "Expand all" reopen it instantly. */

    function setCollapsed(node, collapsed) {
        node.classList.toggle('is-collapsed', collapsed);

        var toggle = node.querySelector(':scope > .tree__row [data-tree-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-tree-toggle]');
        if (toggle) {
            event.preventDefault();
            var node = toggle.closest('.tree__node');
            if (node) setCollapsed(node, !node.classList.contains('is-collapsed'));
            return;
        }

        var bulk = event.target.closest('[data-tree-expand], [data-tree-collapse]');
        if (!bulk) return;

        event.preventDefault();

        var collapse = bulk.hasAttribute('data-tree-collapse');
        var scope = document.getElementById(bulk.dataset.treeExpand || bulk.dataset.treeCollapse);
        if (!scope) return;

        scope.querySelectorAll('.tree__node').forEach(function (node) {
            // Only branches collapse - a leaf has nothing to hide.
            if (node.querySelector(':scope > .tree__children')) setCollapsed(node, collapse);
        });
    });

    /* ===================== Swapping ===================== */

    function progress(on) {
        var bar = document.getElementById('nav-progress');
        if (!bar) return;
        bar.classList.toggle('is-active', !!on);
    }

    function sameOrigin(url) {
        try {
            return new URL(url, location.href).origin === location.origin;
        } catch (error) {
            return false;
        }
    }

    /**
     * Mirror the incoming document's active nav item onto the live sidebar,
     * touching only the class list so no element is torn down.
     */
    function syncNav(doc) {
        var activeHref = null;
        var incoming = doc.querySelector('.sidebar__nav .navlink--active');
        if (incoming) activeHref = incoming.getAttribute('href');

        document.querySelectorAll('.sidebar__nav .navlink').forEach(function (link) {
            var href = link.getAttribute('href');
            link.classList.toggle('navlink--active', href !== null && href === activeHref);
        });
    }

    function swap(result, push) {
        var doc = new DOMParser().parseFromString(result.html, 'text/html');
        var next = doc.querySelector(PAGE);
        var current = document.querySelector(PAGE);

        // Not an admin page: the login screen after a session timeout, or an
        // error page. Navigate for real rather than swapping fragments in.
        if (!next || !current) {
            if (result.url && result.url !== location.href) {
                location.href = result.url;
            } else {
                // Same URL (a 419/500 rendered in place) - show it as-is.
                document.documentElement.innerHTML = doc.documentElement.innerHTML;
            }
            return;
        }

        current.innerHTML = next.innerHTML;
        document.title = doc.title;

        // Sidebar active state lives outside #page. Toggle the class rather
        // than replacing the nav's HTML - destroying the element under the
        // pointer can swallow the user's next click, because a click only
        // fires when mousedown and mouseup land on the same element.
        syncNav(doc);

        // So do the toasts.
        var oldToasts = document.querySelector('.toasts');
        if (oldToasts) oldToasts.remove();
        var newToasts = doc.querySelector('.toasts');
        if (newToasts) document.body.appendChild(newToasts);

        if (push && result.url) history.pushState({ ajax: true }, '', result.url);

        closeNav();
        window.scrollTo(0, 0);
        initPage();
    }

    /* ===================== Reads (links, GET forms) ===================== */

    /** Links that must stay ordinary browser navigations. */
    function isPlainLink(link) {
        var href = link.getAttribute('href');

        return link.hasAttribute('download')
            || link.target === '_blank'
            || link.dataset.noAjax !== undefined
            || href.charAt(0) === '#'
            // Exports stream a file - let the browser download it.
            || /\/export\//.test(href);
    }

    function fetchPage(url) {
        if (cache.has(url)) {
            return Promise.resolve({ html: cache.get(url), url: url, ok: true });
        }

        return request(url).then(function (result) {
            if (result.ok && result.html) {
                // Keep the prefetch cache small - it is a convenience, not a store.
                if (cache.size > 20) cache.delete(cache.keys().next().value);
                cache.set(url, result.html);
            }
            return result;
        });
    }

    function visit(url, push) {
        if (inflight) inflight.cancelled = true;

        var token = { cancelled: false };
        inflight = token;
        progress(true);

        fetchPage(url)
            .then(function (result) {
                if (token.cancelled) return;
                swap(result, push !== false);
            })
            .catch(function () {
                location.href = url;
            })
            .finally(function () {
                if (inflight === token) {
                    inflight = null;
                    progress(false);
                }
            });
    }

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        var link = event.target.closest('a[href]');
        if (!link) return;
        if (!sameOrigin(link.href) || isPlainLink(link)) return;

        event.preventDefault();
        visit(link.href, true);
    });

    /**
     * Warm the cache before the click lands. mouseenter covers hovering;
     * pointerdown covers touch and fast clicks, where there is no hover at
     * all - by the time `click` fires the response is usually already here.
     */
    function prefetchFrom(event) {
        var link = event.target.closest('a[href]');
        if (!link || !sameOrigin(link.href) || isPlainLink(link)) return;
        if (cache.has(link.href)) return;

        fetchPage(link.href).catch(function () { /* best effort */ });
    }

    document.addEventListener('mouseover', prefetchFrom);
    document.addEventListener('pointerdown', prefetchFrom);

    window.addEventListener('popstate', function () {
        visit(location.href, false);
    });

    /* ===================== Writes (POST / PUT / DELETE) ===================== */

    function busy(form, on) {
        var submit = form.querySelector('[type="submit"]');
        if (!submit) return;

        if (on) {
            submit.dataset.label = submit.innerHTML;
            submit.setAttribute('aria-disabled', 'true');
            submit.innerHTML = '<span class="spinner"></span>' + (submit.dataset.busy || 'Saving...');
        } else {
            submit.removeAttribute('aria-disabled');
            if (submit.dataset.label !== undefined) submit.innerHTML = submit.dataset.label;
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        var action = form.getAttribute('action') || location.href;

        if (form.dataset.noAjax !== undefined || !sameOrigin(action)) return;

        /* ---- GET: filter bars ---- */
        if (method === 'get') {
            event.preventDefault();

            var params = new URLSearchParams(new FormData(form));
            // Drop empty filters so the URL stays readable.
            Array.from(params.keys()).forEach(function (key) {
                if (params.get(key) === '') params.delete(key);
            });

            var query = params.toString();
            visit(action + (query ? '?' + query : ''), true);
            return;
        }

        /* ---- Writes ---- */
        event.preventDefault();

        var message = form.dataset.confirm;
        if (message && !window.confirm(message)) return;

        // Block the double submit that creates duplicate records.
        if (form.dataset.submitting === '1') return;
        form.dataset.submitting = '1';
        busy(form, true);
        progress(true);

        // _method spoofing already rides along in the FormData, so this is
        // always a real POST as far as the browser is concerned.
        request(action, { method: 'POST', body: new FormData(form) })
            .then(function (result) {
                // The data changed - every prefetched page is now stale.
                cache.clear();
                swap(result, true);
            })
            .catch(function () {
                // Fall back to a real submit so nothing is silently lost.
                form.dataset.noAjax = '';
                form.submit();
            })
            .finally(function () {
                form.dataset.submitting = '0';
                busy(form, false);
                progress(false);
            });
    });

    /* ================= Per page initialisation ================= */

    function initPage() {
        document.querySelectorAll('.toast').forEach(function (toast) {
            var dismiss = function () {
                toast.style.transition = 'opacity .2s';
                toast.style.opacity = '0';
                setTimeout(function () { toast.remove(); }, 220);
            };

            var button = toast.querySelector('button');
            if (button) button.addEventListener('click', dismiss);

            setTimeout(dismiss, 5000);
        });

        document.querySelectorAll('[data-range-select]').forEach(function (select) {
            var sync = function () {
                var custom = select.value === 'custom';
                select.form.querySelectorAll('[data-range-custom]').forEach(function (node) {
                    node.style.display = custom ? '' : 'none';
                });
            };
            select.addEventListener('change', function () {
                sync();
                if (select.value !== 'custom') select.form.requestSubmit();
            });
            sync();
        });

        document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
            field.addEventListener('change', function () { field.form.requestSubmit(); });
        });

        initHierarchyPickers();
        initBulkSelect();
        initFilePreviews();
        initStackedTables();
    }

    /* ---------- Select-all for bulk assignment ----------

       The toggle lives outside the table it controls, so it names its target
       by id rather than relying on where it sits in the markup. */

    function initBulkSelect() {
        document.querySelectorAll('[data-bulk-toggle]').forEach(function (toggle) {
            var scope = document.getElementById(toggle.dataset.bulkToggle);
            if (!scope) return;

            var items = Array.prototype.slice.call(scope.querySelectorAll('[data-bulk-item]'));
            if (!items.length) return;

            var counter = document.querySelector(
                '[data-bulk-count="' + toggle.dataset.bulkToggle + '"]'
            );

            function sync() {
                var picked = items.filter(function (item) { return item.checked; }).length;

                toggle.checked = picked === items.length;
                // Part-selected reads as neither on nor off.
                toggle.indeterminate = picked > 0 && picked < items.length;

                if (counter) {
                    counter.textContent = picked
                        ? picked + ' of ' + items.length + ' selected'
                        : 'Nothing selected yet';
                }
            }

            toggle.addEventListener('change', function () {
                items.forEach(function (item) { item.checked = toggle.checked; });
                sync();
            });

            items.forEach(function (item) { item.addEventListener('change', sync); });

            sync();
        });
    }

    /* ---------- Company -> Project -> Person dependent dropdowns ----------

       Every option is already on the page, tagged with the level above it, so
       narrowing is instant and needs no request. Options are removed and
       re-added rather than hidden, because `display:none` on an <option> is
       not honoured everywhere. */

    function initHierarchyPickers() {
        var groups = {};

        document.querySelectorAll('select[data-hier-group]').forEach(function (select) {
            var name = select.dataset.hierGroup;
            groups[name] = groups[name] || {};
            groups[name][select.dataset.hier] = select;
        });

        Object.keys(groups).forEach(function (name) { wireHierarchy(groups[name]); });
    }

    function wireHierarchy(group) {
        var company = group.company;
        var project = group.project;
        var person = group.person;

        if (!project && !person) return;

        // Snapshot the full option list once - narrowing takes options out of
        // the DOM, and this is what puts them back.
        [project, person].forEach(function (select) {
            if (select && !select._allOptions) {
                select._allOptions = Array.prototype.slice.call(select.options);
            }
        });

        // project id -> company id, for narrowing people by company alone.
        var projectCompany = {};
        if (project) {
            project._allOptions.forEach(function (option) {
                if (option.value) projectCompany[option.value] = option.dataset.company || '';
            });
        }

        function narrow(select, keep) {
            var wanted = select.value;
            var options = select._allOptions.filter(keep);

            select.textContent = '';
            options.forEach(function (option) { select.appendChild(option); });

            // Keep the choice if it survived the narrowing, otherwise clear it
            // so a stale id is never submitted.
            var kept = options.some(function (option) { return option.value === wanted; });
            select.value = kept ? wanted : '';

            // Scoped to the select's own form so two pickers on one page
            // cannot write into each other's hint.
            var hint = (select.form || document)
                .querySelector('[data-hier-hint="' + select.dataset.hier + '"]');
            if (hint) {
                var empty = options.filter(function (option) { return option.value; }).length === 0;
                hint.hidden = !empty;
                hint.textContent = empty ? (select.dataset.hierEmpty || '') : '';
            }

            return kept ? wanted : '';
        }

        function sync() {
            var companyValue = company ? company.value : '';
            var projectValue = project ? project.value : '';

            if (project) {
                projectValue = narrow(project, function (option) {
                    return !option.value
                        || !companyValue
                        || option.dataset.company === companyValue;
                });
            }

            if (person) {
                narrow(person, function (option) {
                    if (!option.value) return true;

                    var ids = (option.dataset.projects || '').split(',').filter(Boolean);

                    // A project pins the list exactly; a company alone widens
                    // it to everyone working anywhere in that company.
                    if (projectValue) return ids.indexOf(projectValue) !== -1;
                    if (companyValue) {
                        return ids.some(function (id) { return projectCompany[id] === companyValue; });
                    }
                    return true;
                });
            }
        }

        if (company) company.addEventListener('change', sync);
        if (project) project.addEventListener('change', sync);

        sync();
    }

    /* ---------- Tables become cards on mobile ---------- */

    /**
     * Copies each column's header onto its cells as data-label, then marks the
     * table stackable. The CSS only switches to the card layout for tables
     * carrying that class, so with JS off the table keeps its horizontal
     * scroll rather than losing its labels.
     */
    function initStackedTables() {
        document.querySelectorAll('table.data').forEach(function (table) {
            var headers = Array.prototype.map.call(
                table.querySelectorAll('thead th'),
                function (th) {
                    // Sortable headers carry an arrow and a screen-reader note.
                    var clone = th.cloneNode(true);
                    clone.querySelectorAll('.arrow, .sr-only').forEach(function (node) {
                        node.remove();
                    });
                    return clone.textContent.trim().replace(/\s+/g, ' ');
                }
            );

            if (!headers.length) return;

            table.querySelectorAll('tbody tr').forEach(function (row) {
                Array.prototype.forEach.call(row.children, function (cell, index) {
                    if (headers[index] && !cell.hasAttribute('data-label')) {
                        cell.setAttribute('data-label', headers[index]);
                    }
                });
            });

            table.classList.add('is-stacked');

            // The wrapper needs padding once rows are cards, and it no longer
            // needs to scroll. Marked here rather than with :has() so old
            // browsers get the same layout.
            var wrap = table.closest('.table-wrap');
            if (wrap) wrap.classList.add('is-stacked');
        });
    }

    /* ---------- Preview files before they are uploaded ---------- */

    function readableSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function initFilePreviews() {
        document.querySelectorAll('input[type="file"][data-file-preview]').forEach(function (input) {
            var target = document.querySelector('[data-file-preview-for="' + input.id + '"]');
            if (!target) return;

            input.addEventListener('change', function () {
                // Object URLs hold the file in memory until revoked.
                (target._urls || []).forEach(URL.revokeObjectURL);
                target._urls = [];
                target.innerHTML = '';

                var files = Array.prototype.slice.call(input.files || []);
                target.hidden = files.length === 0;

                files.forEach(function (file) {
                    var row = document.createElement('div');
                    row.className = 'attachment';

                    var thumb = document.createElement('span');
                    thumb.className = 'attachment__thumb';

                    if (file.type.indexOf('image/') === 0) {
                        var url = URL.createObjectURL(file);
                        target._urls.push(url);

                        var img = document.createElement('img');
                        img.src = url;
                        img.alt = file.name;
                        thumb.appendChild(img);
                    } else {
                        thumb.textContent = 'PDF';
                    }

                    var meta = document.createElement('span');
                    meta.className = 'attachment__meta';

                    var name = document.createElement('strong');
                    name.textContent = file.name;

                    var size = document.createElement('span');
                    size.className = 'muted small';
                    size.textContent = readableSize(file.size) + ' - not uploaded yet';

                    meta.appendChild(name);
                    meta.appendChild(size);

                    row.appendChild(thumb);
                    row.appendChild(meta);
                    target.appendChild(row);
                });
            });
        });
    }

    window.addEventListener('pageshow', function () { cache.clear(); });

    initPage();
})();
