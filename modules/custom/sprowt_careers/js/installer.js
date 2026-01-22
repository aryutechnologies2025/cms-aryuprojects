(function($, Sprowt, Drupal){
    const Careers = function($form) {
        this.$form = $form;
        this.$template = $form.find('#itemTemplate');
        this.templateInfo = this.$template.data('template-info');
        this.pageTypes = this.$template.data('page-types');
        this.$hiddenField = $form.find('#pages');
        this.$wrap = $form.find('#pagesWrap');
        let t = this;
        for(let prop of ['templateInfo', 'pageTypes']) {
            if(typeof t[prop] === 'string') {
                t[prop] = JSON.parse(t[prop]);
            }
        }
        this.setHtmlFromValue();
        this.events();
    };
    Careers.prototype = $.extend(Careers.prototype, {
        populateTemplateSelectOptions($select, pageType, defaultVal = null) {
            let opts = {};
            $.each(this.templateInfo, function (idx, info) {
                if(info.pageType === pageType) {
                    opts[info.uuid] = info.label;
                }
            });
            if($select.hasClass("select2-hidden-accessible")) {
                $select.select2('destroy');
            }
            $select.html('');
            $.each(opts, function(val, text) {
                let $opt = $('<option></option>');
                $opt.text(text);
                $opt.attr('value', val);
                $select.append($opt);
            });
            if(defaultVal) {
                $select.val(defaultVal);
            }
            let select2Options = Drupal.behaviors.select2.getElementOptions($select);
            $select.select2(select2Options);
        },
        setValueFromHtml() {
            let val = [];
            this.$wrap.find('.pageItem').each(function() {
                let $item = $(this);
                let obj = {};
                obj.includeInMenu = $item.find('.includeInMenu').prop('checked');
                if(obj.includeInMenu) {
                    obj.menuLinkTitle = $item.find('.menuLinkTitle').val();
                    obj.menuLinkWeight = Sprowt.selectValue($item.find('.menuLinkWeight'));
                }
                obj.nodeTitle = $item.find('.nodeTitle').val();
                obj.nodeTemplate = Sprowt.selectValue($item.find('.nodeTemplate'));
                obj.nodePublished = $item.find('.nodePublished').prop('checked');
                if(obj.nodeTitle && obj.nodeTemplate) {
                    val.push(obj);
                }
            });
            this.$hiddenField.val(JSON.stringify(val));
        },
        addObj(obj) {
            let $tmp = Sprowt.getTemplate(this.$template);
            if(!!obj.includeInMenu) {
                $tmp.find('.includeInMenu').prop('checked', true);
                $tmp.find('.menuLinkTitle').val(obj.menuLinkTitle || '');
                $tmp.find('.menuLinkTitle').closest('.form-item').show();
                $tmp.find('.menuLinkWeight').val(obj.menuLinkWeight || 0);
                $tmp.find('.menuLinkWeight').closest('.form-item').show();
                Sprowt.makeJsRequired($tmp.find('.menuLinkTitle'));
                Sprowt.makeJsRequired($tmp.find('.menuLinkWeight'));
            }
            else {
                $tmp.find('.menuLinkTitle').closest('.form-item').hide();
                $tmp.find('.menuLinkWeight').closest('.form-item').hide();
                Sprowt.makeJsUnrequired($tmp.find('.menuLinkTitle'));
                Sprowt.makeJsUnrequired($tmp.find('.menuLinkWeight'));
            }
            $tmp.find('.nodeTitle').val(obj.nodeTitle || '');
            $tmp.find('.nodePublished').prop('checked', !!obj.nodePublished);
            Sprowt.makeJsRequired($tmp.find('.nodeTitle'));
            this.$wrap.append($tmp);
            let $select = $tmp.find('.nodeTemplate');
            Sprowt.makeJsRequired($select);
            if($select.hasClass("select2-hidden-accessible")) {
                $select.select2('destroy');
            }
            if(obj.nodeTemplate) {
                $select.val(obj.nodeTemplate);
            }
            if($tmp.find('.menuLinkWeight').hasClass('select2-hidden-accessible')) {
                $tmp.find('.menuLinkWeight').select2('destroy');
            }
            Sprowt.makeJsRequired($select);
            let select2Options = Drupal.behaviors.select2.getElementOptions($select);
            $select.select2(select2Options);
            $tmp.find('.menuLinkWeight').select2(select2Options);


            if($tmp.find('.nodePublished').hasClass('hidden-true')) {
                $tmp.find('.nodePublished').closest('.form-item').hide();
                $tmp.find('.nodePublished').prop('checked', true);
            }
        },
        setHtmlFromValue() {
            let val = this.$hiddenField.val();
            if(typeof val === 'string') {
                val = JSON.parse(val);
            }
            let t = this;
            $.each(val, function(vdx, obj) {
                t.addObj(obj);
            });
        },
        events() {
            let t = this;
            this.$form.on('click', '#addButton', function(e) {
                e.preventDefault();
                t.addObj({});
                t.setValueFromHtml();
            });
            this.$wrap.on('input', 'input', function(e) {
                t.setValueFromHtml();
            });
            this.$wrap.on('change', 'input[type="checkbox"]', function(e) {
                if($(this).is('.includeInMenu')) {
                    let $pageItem = $(this).closest('.pageItem');
                    let $menuLinkTitle = $pageItem.find('.menuLinkTitle');
                    let $menuLinkWeight = $pageItem.find('.menuLinkWeight');
                    if($(this).prop('checked')) {
                        $menuLinkTitle.closest('.form-item').show();
                        $menuLinkWeight.closest('.form-item').show();
                        Sprowt.makeJsRequired($menuLinkTitle);
                        Sprowt.makeJsRequired($menuLinkWeight);
                    }
                    else {
                        $menuLinkTitle.closest('.form-item').hide();
                        $menuLinkWeight.closest('.form-item').hide();
                        Sprowt.makeJsUnrequired($menuLinkTitle);
                        Sprowt.makeJsUnrequired($menuLinkWeight);
                    }
                }
                t.setValueFromHtml();
            });
            this.$wrap.on('change', 'select', function(e) {
                t.setValueFromHtml();
            });
            this.$wrap.on('click', '.removeItem', function(e) {
                e.preventDefault();
                $(this).closest('.pageItem').remove();
                t.setValueFromHtml();
            });
        }
    });

    Drupal.behaviors.sprowtCareersInstaller = {
        attach: function(context, settings) {
            $(once('sprowtCareersInstaller', '.sprowt-careers-installer-form', context)).each(function() {
                let $form = $(this);
                window.careersInstallers = [];
                window.careersInstallers.push(new Careers($form));
            });
        }
    };


})(jQuery, Sprowt, Drupal);
