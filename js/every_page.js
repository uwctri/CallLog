function conv24to12(ts) {
    let H = +ts.substr(0, 2);
    let h = (H % 12) || 12;
    h = (h < 10)?("0"+h):h;
    return h + ts.substr(2, 3) + (H < 12 ? "am" : "pm");
};

$(document).ready(function () {
    let a = `#form\\[${CTRICallLog.static.instrumentLower}\\]`;
    if ( $(a).next().length ) {
        $(a).next().hide();
        $(a).prop('href',$(a).next().prop('href'));
    }
});