(() => {
    const module = ExternalModules.UWMadison.CallLog;
    module.templates = {};
    $.each($("template[id=CallLog]").prop('content').children, (_, el) => {
        let html = $(el).prop('outerHTML')
        let id = $(el).prop('id')
        if (html.startsWith("<table")) {
            let tmp = html.split("\n")
            html = tmp.slice(1, tmp.length).join('').replace("</table>", "").replace("<tbody>", "").replace("</tbody>", "")
            if (id == "notesEntry") {
                html = html.replace("<tr>", "").replace("</tr>", "")
            }
        }
        module.templates[id] = html;
    });
})();