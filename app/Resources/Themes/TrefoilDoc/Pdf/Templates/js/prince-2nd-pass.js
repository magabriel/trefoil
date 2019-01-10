/**
 * PrinceXML second pass script.
 *
 * Uses the imported javascript file produce the finished index.
 */

/**
 * Rewrite the index generated on the first pass to group together the
 * entries with a contiguous range of pages.
 */
function rewriteIndex() {
    var spans = document.querySelectorAll(".auto-index .backlinks");

    for (var i = 0; i < spans.length; ++i) {
        var span = spans[i];
        span.innerHTML = rewriteSpan(span);
    }
}

/**
 * Rewrite one of the backlink spans.
 *
 * @param span
 * @returns {string}
 */
function rewriteSpan(span) {
    var text = "";
    var range = {
        firstPage: -1,
        firstHref: '',
        lastPage: -1,
        lastHref: ''
    };

    var links = span.getElementsByTagName("a");

    for (var i = 0; i < links.length; ++i) {
        var link = links[i];
        var href = link.getAttribute("href");

        if (!indexEntries[href]) {
            Log.warning("[prince-2nd-pass.js] Unknown index ref: " + href);
            continue;
        }

        var page = indexEntries[href];

        if (range.firstPage === -1) {
            range.firstPage = page;
            range.lastPage = page;
            range.firstHref = href;
            range.lastHref = href;
        } else if (page !== range.lastPage) {
            if (page === range.lastPage + 1) {
                range.lastPage = page;
                range.lastHref = href;
            } else {
                text += createRange(range, text !== "");
                range.firstPage = page;
                range.lastPage = page;
                range.firstHref = href;
                range.lastHref = href;
            }
        }
    }

    if (links.length > 0) {
        text += createRange(range, text !== "");
    }

    return text;
}

function createRange(range, addComma) {
    var text = addComma ? ", " : "";
    if (range.firstPage !== range.lastPage) {
        text += createBackLink(range.firstHref, range.firstPage) + "-" + createBackLink(range.lastHref, range.lastPage);
    } else {
        text += createBackLink(range.firstHref, range.firstPage);
    }
    return text;
}

function createBackLink(href, text) {
    return '<a class="backlink" href="' + href + '">' + text + '</a>';
}

// Tell Prince to call our rewrite process on document load
addEventListener("load", rewriteIndex, false);

