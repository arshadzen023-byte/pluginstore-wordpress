
(function () {
    'use strict';

    if (window.ARKCWWishlistInitialized) {
        return;
    }
    window.ARKCWWishlistInitialized = true;

    var KEY = 'arkCWGuest';

    /* ---------------- Guest wishlist storage ---------------- */

    function guest() {
        try {
            var value = JSON.parse(localStorage.getItem(KEY) || '[]');
            return Array.isArray(value)
                ? value.map(Number).filter(function (id) {
                    return Number.isSafeInteger(id) && id > 0;
                })
                : [];
        } catch (error) {
            return [];
        }
    }

    function save(ids) {
        try {
            localStorage.setItem(
                KEY,
                JSON.stringify(uniq(ids).slice(0, 500))
            );
        } catch (error) {
            // Storage may be unavailable in private browsing.
        }
    }

    function uniq(ids) {
        return Array.from(new Set((ids || []).map(Number).filter(function (id) {
            return Number.isSafeInteger(id) && id > 0;
        }))).slice(0, 500);
    }

    /* ---------------- WordPress AJAX ---------------- */

    function req(action, data) {
        if (!window.ARKCW || !ARKCW.url || !ARKCW.nonce) {
            return Promise.reject(new Error('Wishlist configuration missing'));
        }

        var body = new URLSearchParams();
        body.set('action', 'ark_cw_' + action);
        body.set('nonce', ARKCW.nonce);

        Object.keys(data || {}).forEach(function (key) {
            if (Array.isArray(data[key])) {
                data[key].forEach(function (value) {
                    body.append(key + '[]', String(value));
                });
            } else {
                body.set(key, String(data[key]));
            }
        });

        return fetch(ARKCW.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type':
                    'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed');
            }
            return response.json();
        });
    }

    function ids() {
        if (!ARKCW.logged) {
            return Promise.resolve(guest());
        }

        return req('get', {}).then(function (response) {
            if (!response.success || !response.data) {
                throw new Error('Wishlist could not load');
            }

            var current = uniq(response.data.ids);
            var guestIds = guest();

            if (!guestIds.length) {
                return current;
            }

            return req('sync', { ids: guestIds }).then(function (result) {
                if (result.success && result.data) {
                    save([]);
                    return uniq(result.data.ids);
                }
                return current;
            });
        });
    }

    /* ---------------- Notices and wishlist buttons ---------------- */

    function message(text) {
        document.querySelectorAll('.ark-cw-notice').forEach(function (notice) {
            notice.textContent = text || '';
        });
    }

    function buttons(list) {
        list = uniq(list);

        document.querySelectorAll('.ark-cw-button').forEach(function (button) {
            var active = list.indexOf(Number(button.dataset.id)) !== -1;

            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.setAttribute(
                'aria-label',
                active ? ARKCW.saved : ARKCW.text
            );

            var label = button.querySelector('.ark-cw-label');
            if (label) {
                label.textContent = active ? ARKCW.saved : ARKCW.text;
            }

            var count = button.querySelector('.ark-cw-count');
            if (count) {
                count.textContent = String(list.length);
            }
        });
    }

    /* ---------------- Accessible icon buttons ---------------- */

    function makeIconButton(type, label, path) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'ark-cw-icon-action ark-cw-' + type;
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);

        var svgNS = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');

        var pathElement = document.createElementNS(svgNS, 'path');
        pathElement.setAttribute('d', path);
        pathElement.setAttribute('fill', 'none');
        pathElement.setAttribute('stroke', 'currentColor');
        pathElement.setAttribute('stroke-width', '1.8');
        pathElement.setAttribute('stroke-linecap', 'round');
        pathElement.setAttribute('stroke-linejoin', 'round');

        svg.appendChild(pathElement);
        button.appendChild(svg);

        return button;
    }

    /* ---------------- Wishlist page rendering ---------------- */

    function render(list) {
        document.querySelectorAll('.ark-cw-page').forEach(function (page) {
            var grid = page.querySelector('.ark-cw-grid');
            var total = page.querySelector('.ark-cw-total');

            if (!grid) {
                return;
            }

            if (total) {
                total.textContent =
                    list.length + ' item' + (list.length === 1 ? '' : 's');
            }

            grid.replaceChildren();

            if (!list.length) {
                var empty = document.createElement('div');
                empty.className = 'ark-cw-empty';
                empty.textContent =
                    ARKCW.empty || 'Your wishlist is empty.';
                grid.appendChild(empty);
                return;
            }

            var loading = document.createElement('div');
            loading.className = 'ark-cw-loading';
            loading.textContent = 'Loading wishlist…';
            grid.appendChild(loading);

            Promise.all(list.map(function (id) {
                return fetch(
                    ARKCW.productApi + encodeURIComponent(id),
                    { credentials: 'same-origin' }
                ).then(function (response) {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                }).catch(function () {
                    return null;
                });
            })).then(function (products) {
                grid.replaceChildren();

                products.forEach(function (product) {
                    if (!product || !product.id || !product.permalink) {
                        return;
                    }

                    var item = document.createElement('article');
                    item.className = 'ark-cw-item';

                    /* Product thumbnail */
                    var imageLink = document.createElement('a');
                    imageLink.className = 'ark-cw-image';
                    imageLink.href = product.permalink;
                    imageLink.setAttribute(
                        'aria-label',
                        'View ' + (product.name || 'product')
                    );

                    var img = document.createElement('img');
                    img.loading = 'lazy';
                    img.alt = product.name || 'Product';

                    if (product.images && product.images[0]) {
                        img.src = product.images[0].thumbnail ||
                            product.images[0].src || '';
                    }

                    imageLink.appendChild(img);
                    item.appendChild(imageLink);

                    /* Product details */
                    var body = document.createElement('div');
                    body.className = 'ark-cw-body';

                    var title = document.createElement('a');
                    title.className = 'ark-cw-title';
                    title.href = product.permalink;
                    title.textContent = product.name || 'Product';
                    body.appendChild(title);

                    var price = document.createElement('div');
                    price.className = 'ark-cw-price';

                    // Price HTML is supplied by WooCommerce Store API.
                    price.innerHTML = product.price_html || '';
                    body.appendChild(price);

                    /* View and delete icon buttons */
                    var actions = document.createElement('div');
                    actions.className = 'ark-cw-actions';

                    var view = makeIconButton(
                        'view',
                        'View product',
                        'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z'
                    );

                    view.addEventListener('click', function () {
                        window.location.href = product.permalink;
                    });
                    actions.appendChild(view);

                    var remove = makeIconButton(
                        'delete',
                        'Remove from wishlist',
                        'M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 10v6 M14 10v6'
                    );

                    remove.dataset.remove = String(product.id);
                    actions.appendChild(remove);

                    body.appendChild(actions);
                    item.appendChild(body);
                    grid.appendChild(item);
                });

                if (!grid.children.length) {
                    var noProducts = document.createElement('div');
                    noProducts.className = 'ark-cw-empty';
                    noProducts.textContent =
                        ARKCW.empty || 'Your wishlist is empty.';
                    grid.appendChild(noProducts);
                }
            }).catch(function () {
                grid.replaceChildren();

                var error = document.createElement('div');
                error.className = 'ark-cw-empty';
                error.textContent =
                    ARKCW.error || 'Could not load wishlist.';
                grid.appendChild(error);
            });
        });
    }

    function refresh() {
        ids().then(function (list) {
            list = uniq(list);
            buttons(list);
            render(list);
        }).catch(function () {
            buttons(guest());
            render(guest());
        });
    }

    /* ---------------- Add/remove wishlist products ---------------- */

    function toggle(id) {
        id = Number(id);

        if (!Number.isSafeInteger(id) || id < 1) {
            return;
        }

        if (!ARKCW.logged) {
            var list = guest();
            var adding = list.indexOf(id) === -1;

            list = adding
                ? [id].concat(list)
                : list.filter(function (value) {
                    return value !== id;
                });

            save(list);
            buttons(list);
            render(list);
            message(adding ? 'Added to wishlist' : 'Removed from wishlist');
            return;
        }

        req('toggle', { id: id }).then(function (response) {
            if (!response.success) {
                throw new Error('Wishlist update failed');
            }

            message(
                response.data && response.data.added
                    ? 'Added to wishlist'
                    : 'Removed from wishlist'
            );
            refresh();
        }).catch(function () {
            message(ARKCW.error || 'Could not update wishlist.');
        });
    }

    /* ---------------- Product share helper ---------------- */

    

async function shareProduct(button) {
    var url = button.getAttribute('data-share-url');
    var title = button.getAttribute('data-share-title') || document.title;

    if (!url) {
        url = window.location.href;
    }

    var shareData = {
        title: title,
        text: 'Check out this product: ' + title,
        url: url
    };

    if (navigator.share) {
        try {
            await navigator.share(shareData);
            return;
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
        }
    }

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
            window.alert('Product link copied!');
            return;
        }
    } catch (error) {
        // Continue to manual copy.
    }

    window.prompt('Copy product link:', url);
}



    /* ---------------- Global click handling ---------------- */

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!target || !target.closest) {
            return;
        }

        
var shareButton = target.closest('.ark-cw-share-image');

if (shareButton) {
    event.preventDefault();
    event.stopPropagation();

    if (!shareButton.disabled) {
        shareButton.disabled = true;

        shareProduct(shareButton).finally(function () {
            shareButton.disabled = false;
        });
    }

    return;
}


        var wishlistButton = target.closest('.ark-cw-button');

        if (wishlistButton) {
            event.preventDefault();
            toggle(wishlistButton.dataset.id);
            return;
        }

        var removeButton = target.closest('[data-remove]');

        if (!removeButton) {
            return;
        }

        event.preventDefault();

        var id = Number(removeButton.dataset.remove);

        if (!Number.isSafeInteger(id) || id < 1) {
            return;
        }

        if (!ARKCW.logged) {
            var list = guest().filter(function (value) {
                return value !== id;
            });

            save(list);
            buttons(list);
            render(list);
            message('Removed from wishlist');
            return;
        }

        removeButton.disabled = true;

        req('remove', { id: id }).then(function (response) {
            if (!response.success) {
                throw new Error('Remove failed');
            }

            message('Removed from wishlist');
            refresh();
        }).catch(function () {
            removeButton.disabled = false;
            message(ARKCW.error || 'Could not update wishlist.');
        });
    });

    /* ---------------- Start ---------------- */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refresh);
    } else {
        refresh();
    }
})();
