/*
 * Simple json hightlight (without external libs)
 */
function escapeHtml(text) {
    return text.replace(/[<>&]/g, function(char) {
        return {'<': '&lt;', '>': '&gt;', '&': '&amp;'}[char];
    });
}

function highlightJson(text) {
    return text
        .replace(/(("([^"]|\\")+?[^\\]")|"")(\s*.)/ig, function(str, g1, g2, g3, g4) {
            var type = g4.trim() === ":" ? "json_key" : "json_string";
            return "<span class='" + type + "'>" + escapeHtml(g1) + "</span>" + g4;
        })
        .replace(/-?\d+\.?\d*((E|e)[\+]\d+)?/ig, "<span class='json_number'>$&</span>")
        .replace(/false|true|null/ig, "<span class='json_bool'>$&</span>")
}


document.querySelectorAll('.json').forEach(function (el) {
    el.innerHTML = highlightJson(el.innerHTML);
});
