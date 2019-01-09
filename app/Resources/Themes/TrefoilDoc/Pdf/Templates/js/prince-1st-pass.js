/**
 * PrinceXML first pass script.
 *
 * It collects all the index links and produces a javascript file to be imported
 * during the second pass to produce the finished index.
 */

var indexEntries = {};

/**
 * This function is called from the book's CSS for each index backlink.
 * It collects all the backlinks in the indexEntries object.
 */
function addIndexEntry(href, page) {
    indexEntries[href] = page;
    return page;
}

/**
 * This function will produce the actual javascript file for next pass, as
 * the PrinceXML standard output.
 */
function dumpIndex() {
    console.log("var indexEntries = {");

    var keys = [];
    for (var href in indexEntries) {
        keys.push(href);
    }
    for (var i in keys) {
        var comma = (i < keys.length - 1 ? "," : "");
        console.log("\"" + keys[i] + "\": " + indexEntries[keys[i]] + comma);
    }

    console.log("};");
}

// Tell Prince to use our function
Prince.addScriptFunc("addIndexEntry", addIndexEntry);

// Tell Prince to call our function when document is completely rendered
Prince.addEventListener("complete", dumpIndex, false);
