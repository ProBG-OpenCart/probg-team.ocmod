(function($) {
    'use strict';

    var lang = (document.documentElement.getAttribute('lang') || '').toLowerCase();
    var isBg = lang.indexOf('bg') === 0;
    var text = isBg ? {
        blocks: 'Списък с блокове',
        menus: 'Списък с менюта',
        searchBlocks: 'Търси блок...',
        searchMenus: 'Търси меню...',
        name: 'Име',
        status: 'Статус',
        category: 'Категория',
        limit: 'Лимит',
        details: 'Настройки',
        actions: 'Действия',
        edit: 'Редакция',
        remove: 'Премахни',
        back: 'Към списъка',
        enabled: 'Включен',
        disabled: 'Изключен',
        allCategories: 'Всички категории',
        emptyBlocks: 'Няма създадени блокове.',
        emptyMenus: 'Няма създадени менюта.',
        noResults: 'Няма записи, отговарящи на търсенето.',
        confirmRemove: 'Сигурни ли сте, че искате да премахнете този запис? Промяната ще се приложи след натискане на Запази.',
        saveNote: 'Промените по блоковете и менютата се записват с основния бутон „Запази“.',
        error: 'Има полета за корекция',
        columns: 'колони'
    } : {
        blocks: 'Member blocks',
        menus: 'Menus',
        searchBlocks: 'Search blocks...',
        searchMenus: 'Search menus...',
        name: 'Name',
        status: 'Status',
        category: 'Category',
        limit: 'Limit',
        details: 'Settings',
        actions: 'Actions',
        edit: 'Edit',
        remove: 'Remove',
        back: 'Back to list',
        enabled: 'Enabled',
        disabled: 'Disabled',
        allCategories: 'All categories',
        emptyBlocks: 'No member blocks have been created.',
        emptyMenus: 'No menus have been created.',
        noResults: 'No records match your search.',
        confirmRemove: 'Are you sure you want to remove this record? The change is applied after clicking Save.',
        saveNote: 'Block and menu changes are stored with the main Save button.',
        error: 'Contains fields that need attention',
        columns: 'columns'
    };

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function nextRow($container) {
        var max = -1;
        $container.children('.probg-team-instance-editor').each(function() {
            var row = parseInt($(this).attr('data-instance-row'), 10);
            if (!isNaN(row) && row > max) max = row;
        });
        return max + 1;
    }

    function activateLanguageTabs($scope) {
        $scope.find('.instance-language-tabs').each(function() {
            var $tabs = $(this);
            if (!$tabs.find('li.active').length) $tabs.find('a:first').tab('show');
        });
    }

    function field($editor, suffix) {
        return $editor.find('[name$="[' + suffix + ']"]').first();
    }

    function value($editor, suffix, fallback) {
        var $field = field($editor, suffix);
        return $field.length ? $field.val() : fallback;
    }

    function selectedText($editor, suffix, fallback) {
        var $field = field($editor, suffix);
        if (!$field.length) return fallback;
        return $.trim($field.find('option:selected').text()) || fallback;
    }

    function createEditor(config) {
        var row = nextRow(config.$editors);
        var template = $(config.template).html();
        var $editor = $(template.replace(/__ROW__/g, row));
        config.$editors.append($editor);
        prepareEditor($editor, config);
        return $editor;
    }

    function prepareEditor($editor, config) {
        activateLanguageTabs($editor);
        var $heading = $editor.children('.panel-heading');
        if (!$heading.find('.button-team-instance-list').length) {
            $('<button type="button" class="btn btn-default btn-xs pull-right button-team-instance-list"><i class="fa fa-arrow-left"></i> ' + escapeHtml(text.back) + '</button>')
                .css('margin-right', '6px')
                .insertBefore($heading.find('.button-remove-team-instance').first());
        }
        $editor.attr('data-team-instance-kind', config.kind);
    }

    function instanceInfo($editor, config) {
        var name = $.trim($editor.find('.probg-team-instance-name').val() || config.defaultName);
        var status = String(value($editor, 'status', '1')) === '1';
        var category = selectedText($editor, 'team_category_id', text.allCategories);
        var limit = value($editor, 'limit', '');
        var details = '';

        if (config.kind === 'blocks') {
            details = value($editor, 'columns', '4') + ' ' + text.columns + ' · ' + selectedText($editor, 'sort', '');
        }

        return {
            row: String($editor.attr('data-instance-row')),
            name: name,
            status: status,
            category: category,
            limit: limit,
            details: details,
            hasErrors: $editor.find('.has-error, .text-danger').length > 0
        };
    }

    function initManager(options) {
        var config = $.extend({}, options);
        config.$tab = $(config.tab);
        config.$editors = $(config.editors);
        config.$add = $(config.add);

        if (!config.$tab.length || !config.$editors.length || !config.$add.length) return;

        var managerId = 'probg-team-' + config.kind + '-manager';
        var listId = 'probg-team-' + config.kind + '-list';
        var searchId = 'probg-team-' + config.kind + '-search';
        var countId = 'probg-team-' + config.kind + '-count';
        var managerHtml = '' +
            '<div id="' + managerId + '" class="panel panel-default probg-team-instance-manager">' +
                '<div class="panel-heading clearfix">' +
                    '<h3 class="panel-title pull-left"><i class="fa ' + config.icon + '"></i> ' + escapeHtml(config.title) + ' <span class="badge" id="' + countId + '">0</span></h3>' +
                    '<div class="pull-right probg-team-instance-add-host"></div>' +
                '</div>' +
                '<div class="panel-body">' +
                    '<div class="row probg-team-instance-search"><div class="col-sm-6"><div class="input-group"><span class="input-group-addon"><i class="fa fa-search"></i></span><input type="search" id="' + searchId + '" class="form-control" placeholder="' + escapeHtml(config.search) + '"></div></div></div>' +
                    '<div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr>' +
                        '<th>' + text.name + '</th><th>' + text.status + '</th><th>' + text.category + '</th><th class="text-center">' + text.limit + '</th>' +
                        (config.kind === 'blocks' ? '<th>' + text.details + '</th>' : '') +
                        '<th class="text-right">' + text.actions + '</th>' +
                    '</tr></thead><tbody id="' + listId + '"></tbody></table></div>' +
                    '<div class="alert alert-info probg-team-instance-save-note"><i class="fa fa-info-circle"></i> ' + escapeHtml(text.saveNote) + '</div>' +
                '</div>' +
            '</div>';

        var $manager = $(managerHtml);
        config.$editors.before($manager);
        config.$add.detach().appendTo($manager.find('.probg-team-instance-add-host'));
        config.$tab.find('.probg-team-instance-toolbar').remove();
        config.$editors.addClass('probg-team-instance-editors-managed').hide();

        config.$editors.children('.probg-team-instance-editor').each(function() {
            prepareEditor($(this), config);
        });

        function applyFilter() {
            var query = $.trim($('#' + searchId).val() || '').toLowerCase();
            var visible = 0;
            $('#' + listId + ' tr[data-instance-row]').each(function() {
                var $row = $(this);
                var match = !query || String($row.attr('data-search') || '').indexOf(query) !== -1;
                $row.toggle(match);
                if (match) visible++;
            });
            $('#' + listId + ' .probg-team-no-results').remove();
            if (query && !visible && config.$editors.children('.probg-team-instance-editor').length) {
                $('#' + listId).append('<tr class="probg-team-no-results"><td colspan="' + (config.kind === 'blocks' ? 6 : 5) + '" class="text-center text-muted">' + escapeHtml(text.noResults) + '</td></tr>');
            }
        }

        function renderList() {
            var html = '';
            var count = 0;
            config.$editors.children('.probg-team-instance-editor').each(function() {
                var info = instanceInfo($(this), config);
                var statusClass = info.status ? 'label-success' : 'label-default';
                var statusText = info.status ? text.enabled : text.disabled;
                var errorBadge = info.hasErrors ? ' <span class="label label-danger" title="' + escapeHtml(text.error) + '"><i class="fa fa-exclamation-triangle"></i></span>' : '';
                var search = (info.name + ' ' + info.category + ' ' + info.details).toLowerCase();
                html += '<tr data-instance-row="' + escapeHtml(info.row) + '" data-search="' + escapeHtml(search) + '">' +
                    '<td><strong>' + escapeHtml(info.name) + '</strong>' + errorBadge + '</td>' +
                    '<td><span class="label ' + statusClass + '">' + statusText + '</span></td>' +
                    '<td>' + escapeHtml(info.category) + '</td>' +
                    '<td class="text-center">' + escapeHtml(info.limit) + '</td>' +
                    (config.kind === 'blocks' ? '<td>' + escapeHtml(info.details) + '</td>' : '') +
                    '<td class="text-right"><button type="button" class="btn btn-primary btn-sm button-edit-team-instance" data-kind="' + config.kind + '" data-row="' + escapeHtml(info.row) + '" title="' + escapeHtml(text.edit) + '"><i class="fa fa-pencil"></i></button> ' +
                    '<button type="button" class="btn btn-danger btn-sm button-delete-team-instance-list" data-kind="' + config.kind + '" data-row="' + escapeHtml(info.row) + '" title="' + escapeHtml(text.remove) + '"><i class="fa fa-trash"></i></button></td></tr>';
                count++;
            });
            if (!count) {
                html = '<tr><td colspan="' + (config.kind === 'blocks' ? 6 : 5) + '" class="text-center text-muted">' + escapeHtml(config.empty) + '</td></tr>';
            }
            $('#' + listId).html(html);
            $('#' + countId).text(count);
            applyFilter();
        }

        function openEditor(row) {
            var $editor = config.$editors.children('.probg-team-instance-editor[data-instance-row="' + row + '"]');
            if (!$editor.length) return;
            $manager.hide();
            config.$editors.show();
            config.$editors.children('.probg-team-instance-editor').hide();
            $editor.show();
            activateLanguageTabs($editor);
        }

        function showList() {
            renderList();
            config.$editors.hide();
            config.$editors.children('.probg-team-instance-editor').hide();
            $manager.show();
        }

        $('#' + searchId).on('input', applyFilter);
        config.$add.off('click.probgTeamInstances').on('click.probgTeamInstances', function() {
            var $editor = createEditor(config);
            renderList();
            openEditor(String($editor.attr('data-instance-row')));
        });

        $(document).on('click.probgTeamInstances.' + config.kind, '.button-edit-team-instance[data-kind="' + config.kind + '"]', function() {
            openEditor(String($(this).attr('data-row')));
        });

        $(document).on('click.probgTeamInstances.' + config.kind, '.button-delete-team-instance-list[data-kind="' + config.kind + '"]', function() {
            if (!window.confirm(text.confirmRemove)) return;
            config.$editors.children('.probg-team-instance-editor[data-instance-row="' + $(this).attr('data-row') + '"]').remove();
            renderList();
        });

        config.$editors.on('click', '.button-team-instance-list', showList);
        config.$editors.on('click', '.button-remove-team-instance', function() {
            if (!window.confirm(text.confirmRemove)) return;
            $(this).closest('.probg-team-instance-editor').remove();
            showList();
        });
        config.$editors.on('input', '.probg-team-instance-name', function() {
            var value = $.trim($(this).val()) || config.defaultName;
            $(this).closest('.probg-team-instance-editor').find('.probg-team-instance-title').first().text(value);
        });

        renderList();
        var $error = config.$editors.children('.probg-team-instance-editor').filter(function() {
            return $(this).find('.has-error, .text-danger').length > 0;
        }).first();
        if ($error.length) {
            $('a[href="' + config.tab + '"]').tab('show');
            openEditor(String($error.attr('data-instance-row')));
        }
    }

    $(function() {
        activateLanguageTabs($(document));
        initManager({
            kind: 'blocks', tab: '#tab-blocks', editors: '#probg-team-block-editors', add: '#button-add-team-block', template: '#probg-team-block-template',
            title: text.blocks, search: text.searchBlocks, empty: text.emptyBlocks, icon: 'fa-th-large', defaultName: 'ProBG Team - Members'
        });
        initManager({
            kind: 'menus', tab: '#tab-menu', editors: '#probg-team-menu-editors', add: '#button-add-team-menu', template: '#probg-team-menu-template',
            title: text.menus, search: text.searchMenus, empty: text.emptyMenus, icon: 'fa-bars', defaultName: 'ProBG Team Menu'
        });
    });
})(jQuery);
