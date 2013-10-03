/*global M*/
M.mod_klassenbuch_collapse = {
    init: function(Y) {
        "use strict";
        var toc = new Y.YUI2.widget.TreeView('klassenbuch-toc');
        toc.render();
    }
};