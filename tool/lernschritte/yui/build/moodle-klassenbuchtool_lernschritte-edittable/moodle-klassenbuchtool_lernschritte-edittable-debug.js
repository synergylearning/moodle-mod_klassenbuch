YUI.add('moodle-klassenbuchtool_lernschritte-edittable', function (Y, NAME) {

M.klassenbuch_lernschritte = M.klassenbuch_lernschritte || {};
NS = M.klassenbuch_lernschritte;

NS.init = function(args) {

    // ... the datatable.
    var dt;
    // ... insert new ids with negative id value.
    var insertid = -1;

    /** renders the duration column of the lernschritte table.*/
    function formatDuration(o) {
        return Number(o.data.duration) + "&nbsp;mins";
    }

    /** called, when a row is modified and not saved to database */
    function onRowChanged(o) {

        // ... mark record as unsaved so formatter in options column can visualize unsaved state.
        //o.record.set('savestatus','unsaved');
        // GILMTWO-718 - Save as soon as row is altered
        onClickSave(o.td);
        onTableChanged();
    }

    /** save new sortorder to database */
    function saveSortOrder(node) {

        var params = {};
        params.action = "updatesortorder";
        params.chapterid = args.chapterid;
        params.module = args.module;
        params.sesskey = M.cfg.sesskey;

        var ids = [];
        dt.data.each(function (record, index) {
            ids[index] = record.get('id');
        });
        params.sortorder = ids.join(',');

        var lightbox = M.util.add_lightbox(Y, node);
        var uri = M.cfg.wwwroot + args.ajaxurl;

        Y.io(uri, {
            method: 'POST',
            data: params,
            on: {
                start : function() {
                    lightbox.show();
                },
                success: function(tid, response) {
                    // Update subchapter titles, we can't simply swap them as
                    // they might have custom title
                    try {
                        var responsetext = Y.JSON.parse(response.responseText);
                        if (responsetext.error) {
                            new M.core.ajaxException(responsetext);
                        }
                    } catch (e) {}

                    window.setTimeout(function() {
                        lightbox.hide();
                    }, 250);
                },
                failure: function(tid, response) {
                    alert(response);
                    lightbox.hide();
                }
            }
        });
    }

    /** initialize the lernschritte table*/
    function initTable() {

        // new Editortype for proper display of Starttime column.
        Y.DataTable.EditorOptions.time = {
            BaseViewClass:  Y.DataTable.BaseCellPopupEditor,
            name:           'time',
            templateObject:{
                html: '<input type="text" title="inline cell editor" class="<%= this.classInput %>"  />'
            },

            inputKeys: true,
            keyFiltering:   /\d|\:/,
            validator:  /^(\d{1,2})(\:)(\d{2})$/,

            saveFn: function(v){

                var vre = this.get('validator'), value;

                if(vre instanceof RegExp) {
                    value = (vre.test(v)) ? v : undefined;
                } else {
                    value = v;
                }
                return value;
            },

            after: {

                editorShow : function(o){
                    o.inputNode.focus();
                    o.inputNode.select();
                }
            }
        };

        Y.DataTable.EditorOptions.textarea.after = {

            editorShow : function(o){
                o.inputNode.focus();
                o.inputNode.select();
                if (o.value === "") {
                    o.inputNode.set('value',"");
                }
            }
        };

        // create a object of the table.
        dt = new Y.DataTable({

            columns : [
            {
                key:'id',
                label: 'id',
                editable: false
            },
            {
                key: 'options',
                label : M.str.klassenbuchtool_lernschritte.options,
                editable : false,
                allowHTML : true,
                formatter: function (o) {
                    if ((o.data.savestatus) &&  (o.data.savestatus === 'unsaved')) {
                        o.rowClass = 'unsaved';
                    }
                    return o.data.options.value;
                }
            },
            {
                key: 'attendancetype',
                label : M.str.klassenbuchtool_lernschritte.attendancetype,
                editor : 'select',
                editorConfig:{
                    selectOptions: args.options.attendancetype
                }
            },
            {
                key: 'starttime',
                label : M.str.klassenbuchtool_lernschritte.starttime,
                editor:"time"
            },
            {
                key: 'duration',
                label : M.str.klassenbuchtool_lernschritte.duration,
                formatter: formatDuration,
                allowHTML : true
            },
            {
                key: 'learninggoal',
                label : M.str.klassenbuchtool_lernschritte.learninggoal,
                editor : 'textarea'
            },
            {
                key: 'learningcontent',
                label : M.str.klassenbuchtool_lernschritte.learningcontent,
                editor : 'textarea'
            },
            {
                key: 'collaborationtype',
                label : M.str.klassenbuchtool_lernschritte.collaborationtype,
                editor : 'select',
                editorConfig:{
                    selectOptions: args.options.collaborationtype
                }
            },
            {
                key: 'learnersactivity',
                label : M.str.klassenbuchtool_lernschritte.learnersactivity,
                editor : 'textarea'
            },
            {
                key: 'teachersactivity',
                label : M.str.klassenbuchtool_lernschritte.teachersactivity,
                editor : 'textarea'
            },
            {
                key: 'usedmaterials',
                label : M.str.klassenbuchtool_lernschritte.usedmaterials,
                editor : 'textarea'
            },
            {
                key: 'homework',
                label : M.str.klassenbuchtool_lernschritte.homework,
                editor : 'textarea'
            }],

            data: args.data,
            editable: true,
            defaultEditor: 'text',
            editOpenType: 'click'

        });

        // ... remove options, when user has no cap to edit.
        if (args.nooptions) {
            dt.removeColumn('options');
        }

        dt.render('#dtable');

        dt.after('cellEditorSave', function(o){
            onRowChanged(o);
        });


        // ... add drag and drop functionality.
        var dtBB = dt.get('boundingBox').one('.yui3-datatable-data');
        var firstRow = dtBB.one('tr');

        var firstIndex = -1;
        if (firstRow) {
            firstIndex = dtBB.one('tr').get('rowIndex');
        }

        var startIndex = -1;
        var endIndex = -1;

        var sortable = new Y.Sortable({
            container: dtBB,
            nodes: 'tr',
            opacity: '.1'
        });

        sortable.delegate.after('drag:start', function () {
            var index = sortable.delegate.get('currentNode').get('rowIndex');
            startIndex = index - firstIndex;
        });

        sortable.delegate.after('drag:end', function () {
            var index = sortable.delegate.get('currentNode').get('rowIndex');
            endIndex = index - firstIndex;

            // resort Data
            var dtData = dt.get('data');
            dtData.add(dtData.remove(startIndex), {
                index: endIndex,
                silent: true
            });

            // reset the dtData to restripe
            dtData.reset(dtData);

            // resync to reattch the sort
            sortable.sync();

            // save sortorder and update Table.
            saveSortOrder(sortable.delegate.get('currentNode'));
            onTableChanged();
        });
    }

    /** delete row and save to database */
    function onClickDelete(node) {

        if (confirm(M.str.klassenbuchtool_lernschritte.confirmdelete)) {

            var row = node.ancestor('tr');
            // for example "model_1"
            var recordid = row.getAttribute('data-yui3-record');
            var record = dt.getRecord(recordid);

            var params = {};
            params.action = "delete";
            params.chapterid = args.chapterid;
            params.module = args.module;
            params.sesskey = M.cfg.sesskey;
            params.id = record.get('id');

            // send sortorder to update, when deleting row.
            var ids = [];
            dt.data.each(function (record, index) {
                var rid = record.get('id');
                if (record.id !== rid) {
                    ids[index] = rid;
                }
            });
            params.sortorder = ids.join(',');

            // Add lightbox if it not there
            var lightbox = M.util.add_lightbox(Y, node);

            // Do AJAX request
            var uri = M.cfg.wwwroot + args.ajaxurl;

            Y.io(uri, {
                method: 'POST',
                data: params,
                on: {
                    start : function() {
                        lightbox.show();
                    },
                    success: function(tid, response) {
                        // Update subchapter titles, we can't simply swap them as
                        // they might have custom title
                        try {

                            var responsetext = Y.JSON.parse(response.responseText);
                            if (responsetext.error) {
                                new M.core.ajaxException(responsetext);
                            } else {
                                dt.removeRow(recordid);
                                onTableChanged();
                            }
                        } catch (e) {}

                        window.setTimeout(function() {
                            lightbox.hide();
                        }, 250);
                    },
                    failure: function(tid, response) {
                        alert(response);
                        lightbox.hide();
                    }
                }
            });
        }
    }

    /** save row to database */
    function onClickSave(node) {

        var row = node.ancestor('tr');
        // for example "model_1"
        var recordid = row.getAttribute('data-yui3-record');
        var record = dt.getRecord(recordid);

        // get parameters
        var params = {};
        params.action = "save";
        params.chapterid = args.chapterid;
        params.module = args.module;
        params.sesskey = M.cfg.sesskey;

        for (var i = 0; i < args.colkeys.length; i++) {
            params[args.colkeys[i]] =  record.get(args.colkeys[i]);
        }

        // send sortorder to update, when saving inserted row.
        var ids = [];
        dt.data.each(function (record, index) {
            ids[index] = record.get('id');
        });
        params.sortorder = ids.join(',');

        // Add lightbox if it not there
        var lightbox = M.util.add_lightbox(Y, node);

        // Do AJAX request
        var uri = M.cfg.wwwroot + args.ajaxurl;

        Y.io(uri, {
            method: 'POST',
            data: params,
            on: {
                start : function() {
                    lightbox.show();
                },
                success: function(tid, response) {
                    // Update subchapter titles, we can't simply swap them as
                    // they might have custom title
                    try {

                        var responsetext = Y.JSON.parse(response.responseText);
                        if (responsetext.error) {
                            new M.core.ajaxException(responsetext);
                        } else {
                            dt.modifyRow(recordid, {
                                id: responsetext.lernschritt.id
                            });
                            record.set('savestatus', 'saved');
                            onTableChanged();
                        }
                    } catch (e) {}

                    window.setTimeout(function() {
                        lightbox.hide();
                    }, 250);
                },
                failure: function(tid, response) {
                    alert(response);
                    lightbox.hide();
                }
            }
        });
    }

    function onTableChanged() {
        // ...reattach ActionListener afer table is changed.
        attachActionListener();
    }

    /** add row to table, note that row is not saved to databse automatically */
    function onClickAdd() {

        var newstarttime = "00:00";

        try {
            // get the last row and calculated the starttime.
            var lastitem = dt.data.item(dt.data.size() - 1);
            var starttime = lastitem.get('starttime');
            var duration = lastitem.get('duration');
            var time = starttime.split(':');
            var totalminutes = Number(time[0]) * 60 + Number(time[1]) + Number(duration);
            var hour = Math.floor(totalminutes / 60);
            var minutes = Number(totalminutes % 60);
            newstarttime =  hour + ":" + ('0' + minutes).slice(-2);

        } catch (e) {}

        // setup the new record.
        var newrecord = {};
        for (var i = 0; i < args.colkeys.length; i++) {
            newrecord[args.colkeys[i]] =  "";
        }
        newrecord.id = insertid;
        newrecord.starttime = newstarttime;
        newrecord.duration = 0;
        newrecord.options = args.actions;
        newrecord.savestatus = 'unsaved';

        dt.addRow(newrecord);
        insertid = insertid - 1;
        onTableChanged();
    }

    function attachActionListener() {

        Y.all("a[id^='save']").each(function(node) {

            node.on('click', function(e) {
                e.preventDefault();
                onClickSave(e.currentTarget);
            });
        });

        Y.all("a[id^='delete']").each(function(node) {

            node.on('click', function(e) {
                e.preventDefault();
                onClickDelete(e.currentTarget);
            });
        });
    }

    function initialize() {

        // ... fill existing last column.
        for (var i = 0; i < args.data.length; i++) {
            args.data[i].options = args.actions;
        }

        initTable();
        attachActionListener();

        Y.one('#addrow').on('click', function(e) {
            e.preventDefault();
            onClickAdd();
        });

        new Y.Resize({
            node: '#page-mod-klassenbuch-classplan textarea'
        });
    }

    initialize();
};

}, '@VERSION@', {
    "requires": [
        "node",
        "event-key",
        "io",
        "datatype-date",
        "datatable",
        "sortable",
        "datatable-mutable",
        "gallery-datatable-editable",
        "gallery-datatable-celleditor-popup",
        "gallery-datatable-celleditor-inline",
        "resize"
    ]
});
