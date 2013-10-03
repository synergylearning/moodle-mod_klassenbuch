/*global M, tinyMCE*/
M.mod_klassenbuch_autosave = {
    Y: null,
    enableautosave: true,

    init: function(Y, opts) {
        "use strict";
        this.Y = Y;

        if (opts.autosaveseconds) {
            var self = this;
            setInterval(function() {
                self.kbautosave(opts.formid);
            }, (parseInt(opts.autosaveseconds, 10) * 1000));
        }
    },

    kbautosave: function(formid) {
        "use strict";
        if (!this.enableautosave) {
            return;
        }

        tinyMCE.triggerSave();
        var formObject = document.getElementById(formid);
        formObject.thisisautosave.value = 1;

        var self = this;
        var url = M.cfg.wwwroot + '/mod/klassenbuch/editbrain.php';
        var config = {
            method: 'post',
            form: {
                id: formid
            },
            on: {
                success: function(id, resp) {
                    var formobj = document.getElementById(formid);
                    var chapteridnode = formobj.id;
                    var newchapternode = formobj.newchapter;
                    if (chapteridnode) {
                        var chapterid = parseInt(chapteridnode.value, 10);
                        var respparsed = self.Y.JSON.parse(resp.responseText);
                        if(respparsed !== undefined) {
                            if (chapterid === 0 || isNaN(chapterid)) {
                                if(respparsed.newid !== undefined) {
                                    chapteridnode.value = respparsed.newid;
                                }
                                if(respparsed.newchapter !== undefined) {
                                    newchapternode.value = respparsed.newchapter;
                                }
                            }
                            if(respparsed.success === 1) {
                                var d = new Date();
                                var timestring = d.toLocaleTimeString();
                                var autodisdiv = document.getElementById('autosaveddisplay');
                                autodisdiv.innerHTML = M.util.get_string('autosavedon', 'mod_klassenbuch') + ': ' + timestring;
                            }
                        }
                    }
                }
            }
        };

        this.Y.io(url, config);
    }
};