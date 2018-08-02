(function($) {
    $.fn.iSearch = function(config) {
        var selector = {
            container: '*[role="isearch-container-{id}"]',
            product: '.isearch-product',
            more: '.isearch-more',
            image: '.isearch-product-image img',
            product_info: '.isearch-product-info'
        };

        var template = {
            container: 'script[type="text/template"][role="isearch-container"]',
            loading: 'script[type="text/template"][role="isearch-loading"]',
            product: 'script[type="text/template"][role="isearch-product"]',
            nothing: 'script[type="text/template"][role="isearch-nothing"]',
            more: 'script[type="text/template"][role="isearch-more"]',
            price: 'script[type="text/template"][role="isearch-price"]',
            special: 'script[type="text/template"][role="isearch-special"]',
            suggestion: 'script[type="text/template"][role="isearch-suggestion"]',
            heading_suggestion: 'script[type="text/template"][role="isearch-heading-suggestion"]',
            heading_product: 'script[type="text/template"][role="isearch-heading-product"]'
        };

        var typewatch = (function() {
            var timer = 0;

            return function(callback) {
                clearTimeout(timer);
                timer = setTimeout(callback, config.delay);
            };
        })();

        var htmlEntities = function(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        };

        var getTemplate = function(template, data) {
            var html = $(template).text();

            if (data) {
                for (var i in data) {
                    var regex = new RegExp('{' + i + '}', 'g');

                    html = html.replace(regex, data[i]);
                }
            }

            return html;
        };

        var iSearchCache = function(type, useLocalStorage) {
            var prefix = config.localStoragePrefix + '/' + type + '/';
            var runtimeCache = {};

            var isLocalStorageAvailable = function() {
                try {
                    var storage = window['localStorage'],
                        x = '__storage_test__';
                    storage.setItem(x, x);
                    storage.removeItem(x);

                    return !!useLocalStorage;
                } catch (e) {
                    return false;
                }
            };

            return {
                has: function(key) {
                    if (isLocalStorageAvailable()) {
                        return localStorage.getItem(prefix + key) != null;
                    } else {
                        return typeof runtimeCache[prefix + key] != 'undefined';
                    }
                },
                get: function(key) {
                    if (isLocalStorageAvailable()) {
                        return JSON.parse(localStorage.getItem(prefix + key));
                    } else {
                        return JSON.parse(runtimeCache[prefix + key]);
                    }
                },
                set: function(key, value) {
                    if (typeof value == 'undefined') {
                        return;
                    }

                    if (isLocalStorageAvailable()) {
                        localStorage.setItem(prefix + key, JSON.stringify(value));
                    } else {
                        runtimeCache[prefix + key] = JSON.stringify(value);
                    }
                }
            };
        };

        var improveKeywords = function(value) {
            // Custom Spell Check
            if (config.spell) {
                for (var i in config.spell) {
                    var search = config.spell[i].search;
                    var replace = config.spell[i].replace;

                    if (!search) continue;

                    if (search.indexOf('/') === 0) {
                        var modifiers = '';

                        search = search.replace(/^\/(.*)\/([a-z])*$/i, function(match, p1, p2) {
                            modifiers = 'g' + p2.replace('g', '');
                            return p1;
                        });

                        search = new RegExp(search, modifiers);
                    }

                    value = value.replace(search, replace);
                }
            }

            // Singularize (works only when strict search is disabled)
            if (config.singularization && config.strictness != 'high') {
                var words = value.split(' ');
                var result = [];

                $(words).each(function(index, element) {
                    result.push(element.replace(/(s|es)$/, ''));
                });

                value = result.join(' ');
            }

            // Remove extra spaces
            if (config.strictness != 'high') {
                value = value.replace(/\s+/, ' ').trim();
            }

            // Convert to lowercase, and resolve HTML characters
            value = htmlEntities(value.toLowerCase());

            if (config.strictness == 'high') {
                return [value];
            } else {
                return value.split(' ');
            }
        };

        var keywordCache = iSearchCache('keyword', false);

        var browserSearch = (function() {
            var keyword;
            var originalKeyword;
            var afterLoad;
            var loading = true;
            var productCache = iSearchCache('product', true);
            var data = {};
            var order = 'ASC';
            var xhr;

            var getKeywords = function() {
                return keyword;
            };

            var isLowStrictnessMatch = function(search_data) {
                var is_match = false;

                $(getKeywords()).each(function(index, keyword) {
                    if (search_data.indexOf(keyword) >= 0) {
                        is_match = true;
                        // Stop iteration because we already have a match
                        return false;
                    }
                });

                return is_match;
            };

            var isHighStrictnessMatch = function(search_data) {
                var all_matches = true;

                $(getKeywords()).each(function(index, keyword) {
                    all_matches = all_matches && search_data.indexOf(keyword) >= 0;
                });

                return all_matches;
            };

            var fieldMatchSort = function(field) {
                var full_phrase = getKeywords().join(' ');
                var keywords = full_phrase.split(' ');
                var magnitude = keywords.length;

                // Full phrase match
                if (field.indexOf(full_phrase) === 0) {
                    // At the beginning
                    return magnitude * 3 + 1;
                } else if (field.indexOf(full_phrase) > 0) {
                    // Somewhere in the field after the beginning
                    return magnitude * 2 + 1;
                }

                // Single keyword matches
                var count = 0;
                var at_beginning = false;

                for (var i = 0; i < keywords.length; i++) {
                    if (!at_beginning) {
                        at_beginning = field.indexOf(keywords[i]) === 0;
                    }

                    count += field.indexOf(keywords[i]) >= 0;
                }

                if (at_beginning) {
                    return magnitude + count;
                } else {
                    return count;
                }
            };

            var getSort = function(product) {
                if (config.sort.indexOf('length_') === 0) {
                    return product.s[config.languageId];
                } else if (config.sort.indexOf('match_') === 0) {
                    var best_match = 0;
                    var candidate_match;
                    var fields = product.s[config.languageId].split('|');

                    for (var i in fields) {
                        candidate_match = fieldMatchSort(fields[i].trim());

                        if (candidate_match > best_match) {
                            best_match = candidate_match;
                        }
                    }

                    return best_match;
                } else if (typeof product.s != 'undefined') {
                    return product.s;
                } else {
                    return 0;
                }
            };

            var fetchProductData = function(product_ids, total_found, callback) {
                if (xhr) xhr.abort();

                xhr = $.ajax({
                    url: config.fetchDataURL,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        product_ids: product_ids,
                        keyword: originalKeyword,
                        products: total_found
                    },
                    success: function(json) {
                        callback({
                            products: json.products,
                            suggestions: json.suggestions,
                            more: json.more,
                            has_more: total_found > config.productLimit
                        });
                    }
                });
            };

            var internalSearch = function(callback) {
                var result = [];
                var matches = [];

                $(data).each(function(index, product) {
                    var is_match = config.strictness == 'low' ?
                        isLowStrictnessMatch(product.d) :
                        isHighStrictnessMatch(product.d);

                    if (is_match) {
                        matches.push({
                            product_id: product.i,
                            sort: getSort(product)
                        });
                    }
                });

                matches.sort(function(a, b) {
                    var x = a['sort'];
                    var y = b['sort'];

                    if (x == y) {
                        return a.product_id < b.product_id ? -1 : 1;
                    }

                    if (order == 'ASC') {
                        return (x < y) ? -1 : ((x > y) ? 1 : 0);
                    } else {
                        return (x > y) ? -1 : ((x < y) ? 1 : 0);
                    }
                });

                result = $.map(matches.slice(0, config.productLimit), function(e) {
                    return e.product_id;
                });

                fetchProductData(result, matches.length, callback);
            };

            var internalLoad = function() {
                var stamp = productCache.has('stamp') && productCache.has('data') ? productCache.get('stamp') : '';

                $.ajax({
                    url: config.browserURL.replace('{STAMP}', stamp),
                    type: 'GET',
                    dataType: 'json',
                    beforeSend: function() {
                        loading = true;
                    },
                    success: function(response) {
                        if (response.stamp_status) {
                            data = productCache.get('data');
                            order = productCache.get('order');
                        } else {
                            data = response.products;
                            order = response.order;

                            productCache.set('data', response.products);
                            productCache.set('stamp', response.stamp);
                        }
                    },
                    complete: function() {
                        loading = false;

                        if (typeof afterLoad == 'function') {
                            afterLoad();
                        }
                    }
                });
            };

            var isEnabled = function() {
                return config.type == 'browser';
            };

            if (isEnabled()) {
                internalLoad();
            }

            return {
                isEnabled: isEnabled,
                isLoading: function() {
                    return loading;
                },
                setKeyword: function(value) {
                    originalKeyword = value;
                    keyword = improveKeywords(value);
                },
                setAfterLoad: function(callback) {
                    afterLoad = callback;
                },
                getResults: function(callback) {
                    typewatch(function() {
                        internalSearch(callback);
                    });
                },
                load: internalLoad
            };
        })();

        var highlight = (function() {
            var keyword;

            var insertHighlight = function(text, bounds) {
                var result = '';

                for (var i = 0; i < text.length + 1; i++) {
                    $(bounds).each(function(index, bound) {
                        if (bound.start == i) {
                            result += '<span style="background-color:' + config.highlightColor + '">';
                        }

                        if (bound.finish == i) {
                            result += '</span>';
                        }
                    });

                    if (i < text.length) {
                        result += text[i];
                    }
                }

                return result;
            };

            var highlightText = function(text) {
                var lowercaseText = text.toLowerCase();
                var highlightBounds = [];

                $(keyword).each(function(index, element) {
                    var occurrenceStart = lowercaseText.indexOf(element);

                    if (occurrenceStart >= 0) {
                        highlightBounds.push({
                            start: occurrenceStart,
                            finish: occurrenceStart + element.length
                        });
                    }
                });

                if (config.highlight && highlightBounds.length) {
                    return insertHighlight(text, highlightBounds);
                } else {
                    return text;
                }
            };

            return {
                setKeyword: function(value) {
                    keyword = htmlEntities(value.toLowerCase()).split(' ');
                },
                apply: highlightText
            };
        })();

        var ajaxSearch = (function() {
            var keyword;
            var xhr;

            var doSearch = function(callback) {
                if (xhr) xhr.abort();

                xhr = $.ajax({
                    url: config.ajaxURL.replace('{KEYWORD}', keyword),
                    type: 'GET',
                    dataType: 'json',
                    success: callback
                });
            };

            return {
                isEnabled: function() {
                    return config.type == 'ajax';
                },
                setKeyword: function(value) {
                    keyword = value;
                },
                getResults: function(callback) {
                    typewatch(function() {
                        doSearch(callback);
                    });
                }
            };
        })();

        var init = function(index, element) {
            var selectorContainer = selector.container.replace('{id}', index);

            var adaptContainer = function() {
                $(selectorContainer).css({
                    'left': $(element).offset().left + 1 + 'px',
                    'position': 'absolute',
                    'top': $(element).offset().top + $(element).outerHeight() + 'px',
                    'min-width': config.minWidth,
                    'width': config.widthType == 'fixed' ?
                        config.widthValue + config.widthUnit :
                        $(element).outerWidth() + 'px',
                    'max-height': config.heightType == 'maximum' ?
                        config.heightValue + config.heightUnit :
                        'auto',
                    'overflow-y': config.heightType == 'maximum' ? 'visible' : 'inherit',
                    'overflow-x': config.heightType == 'maximum' ? 'hidden' : 'inherit',
                    'z-index': config.zIndex
                });

                if (config.showImages && config.autoscaleImages) {
                    var maxImageWidth = $(selectorContainer).find(selector.product).width() / 4;
                    var currentWidth = maxImageWidth > config.imageWidth ? config.imageWidth : maxImageWidth;

                    $(selectorContainer).find(selector.image).css({
                        'max-width': maxImageWidth + 'px'
                    });

                    $(selectorContainer).find(selector.product_info).css({
                        'margin-left': currentWidth + 'px'
                    });
                }
            };

            var showContainer = function() {
                $(selectorContainer).show();
            };

            var hideContainer = function(callback) {
                if ($(selectorContainer).length) {
                    $(selectorContainer).hide();
                }

                if (typeof callback == 'function') {
                    callback();
                }
            };

            var renderContainer = function(html) {
                if ($(selectorContainer).length == 0) {
                    $('body').append(getTemplate(template.container, {
                        id: index,
                        content: html
                    }));
                } else {
                    $(selectorContainer).html(html);
                }

                $(selectorContainer).show();

                adaptContainer();
            };

            var renderLoading = function() {
                renderContainer(getTemplate(template.loading, {
                    text: config.textLoading
                }));
            };

            var renderResults = function(results) {
                var html = '';

                if (results.products.length == 0) {
                    html += getTemplate(template.nothing, {
                        text: config.textNothing
                    });
                } else {
                    // Suggestions
                    if (results.suggestions.length) {
                        html += getTemplate(template.heading_suggestion);

                        $(results.suggestions).each(function(index, suggestion) {
                            html += getTemplate(template.suggestion, suggestion);
                        });
                    }

                    // Products heading
                    html += getTemplate(template.heading_product);

                    // Products
                    $(results.products).each(function(index, product) {
                        if (product.special) {
                            product.price = getTemplate(template.special, {
                                price: product.price,
                                special: product.special
                            });
                        } else {
                            product.price = getTemplate(template.price, {
                                price: product.price
                            });
                        }

                        product.alt = product.model;
                        product.name = highlight.apply(product.name);
                        product.model = highlight.apply(product.model);

                        html += getTemplate(template.product, product);
                    });

                    // Show more
                    html += getTemplate(template.more, {
                        href: results.more,
                        text: config.textMore
                    });
                }

                renderContainer(html);

                return results;
            };

            var unbindAnyEvents = function() {
                $(element).off().unbind();
            };

            var performSearch = function() {
                var searchValue = $(element).val().toLowerCase().trim(' ').replace(/\s+/g, ' ');

                if (browserSearch.isEnabled()) {
                    browserSearch.setKeyword(searchValue);
                } else if (ajaxSearch.isEnabled()) {
                    ajaxSearch.setKeyword(searchValue);
                }

                highlight.setKeyword($(element).val());

                if (searchValue == '') {
                    return;
                }

                if (keywordCache.has(searchValue)) {
                    renderResults(keywordCache.get(searchValue));
                } else if (browserSearch.isEnabled()) {
                    renderLoading();

                    var performBrowserSearch = function() {
                        browserSearch.getResults(function(results) {
                            keywordCache.set(searchValue, results);
                            renderResults(results);
                        });
                    };

                    if (browserSearch.isLoading()) {
                        browserSearch.setAfterLoad(performBrowserSearch);
                    } else {
                        performBrowserSearch();
                    }
                } else if (ajaxSearch.isEnabled()) {
                    renderLoading();

                    ajaxSearch.getResults(function(results) {
                        keywordCache.set(searchValue, results);
                        renderResults(results);
                    });
                }
            };

            var keyAction = (function() {
                var selectNext = function() {
                    showContainer();

                    var tabbables = $(selectorContainer).find('*[role="isearch-tab"]');
                    var next = 0;

                    $(tabbables).each(function(index, element) {
                        if ($(element).hasClass('active')) {
                            next = index+1;

                            $(element).removeClass('active');
                        }
                    });

                    if (next >= $(tabbables).length) {
                        next = 0;
                    }

                    $(tabbables[next]).addClass('active');

                    return false;
                };

                var selectPrevious = function() {
                    showContainer();

                    var tabbables = $(selectorContainer).find('*[role="isearch-tab"]');
                    var previous = $(tabbables).length - 1;

                    $(tabbables).each(function(index, element) {
                        if ($(element).hasClass('active')) {
                            previous = index-1;

                            $(element).removeClass('active');
                        }
                    });

                    if (previous < 0) {
                        previous = $(tabbables).length - 1;
                    }

                    $(tabbables[previous]).addClass('active');

                    return false;
                };
                
                var unselect = function() {
                    if ($(selectorContainer).find('.active[role="isearch-tab"]').length) {
                        $(selectorContainer).find('*[role="isearch-tab"]').removeClass('active');
                    } else {
                        hideContainer();
                        $(element).blur();
                    }

                    return false;
                };
                
                var followLink = function(special) {
                    var destination;

                    if ($(selectorContainer).find('.active[role="isearch-tab"]').length) {
                        destination = $(selectorContainer).find('.active[role="isearch-tab"]').attr('href');
                    } else if ($(selectorContainer).find(selector.more).length) {
                        destination = $(selectorContainer).find(selector.more).attr('href');
                    } else {
                        destination = config.moreURL.replace('{KEYWORD}', $(element).val());
                    }

                    if (special) {
                        window.open(destination);
                    } else {
                        location = destination;
                    }
                };

                var trueValue = function() {
                    return true;
                }

                var action = {
                    // TAB:
                    9: function(special) {
                        return special ? selectPrevious() : selectNext();
                    },
                    // UP:
                    38: selectPrevious,
                    // LEFT:
                    37: trueValue,
                    // RIGHT:
                    39: trueValue,
                    // SHIFT:
                    16: trueValue,
                    // CTRL:
                    17: trueValue,
                    // ALT:
                    18: trueValue,
                    // DOWN:
                    40: selectNext,
                    // ESC:
                    27: unselect,
                    // ENTER:
                    13: followLink
                }

                var hasAction = function(key) {
                    return typeof action[key] == 'function';
                }

                return {
                    trigger: function(key, special) {
                        if (hasAction(key)) {
                            return action[key](special);
                        } else {
                            hideContainer(performSearch);

                            return true;
                        }
                    },
                    hasAction: hasAction
                }
            })();

            var bindSearch = function() {
                $(element).keydown(function(e) {
                    if (keyAction.hasAction(e.which)) {
                        return keyAction.trigger(e.which, e.altKey || e.ctrlKey || e.shiftKey);
                    }
                });

                $(element).keyup(function(e) {
                    if (keyAction.hasAction(e.which)) {
                        return false;
                    } else {
                        return keyAction.trigger(e.which);
                    }
                });
            };

            var bindResizeEvent = function() {
                $(window).resize(adaptContainer).trigger('resize');
            };

            var bindDocumentClickEvent = function() {
                $(document).click(function(e) {
                    if ($(e.target).closest(selectorContainer).length == 0 && !$(e.target).is(element)) {
                        hideContainer();
                    }
                });
            };

            var bindFocusEvent = function() {
                $(element).focus(function() {
                    performSearch();
                    scrollMobile();
                });
            };

            var scrollMobile = function() {
                if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                    $('html, body').animate({scrollTop: $(element).offset().top - 10}, config.mobileScrollSpeed);
                }
            };

            unbindAnyEvents();
            bindSearch();
            bindResizeEvent();
            bindDocumentClickEvent();
            bindFocusEvent();

            return element;
        };

        return this.each(init);
    };
}(jQuery));
