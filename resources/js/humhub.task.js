/*
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2017 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 *
 */
humhub.module('task', function (module, require, $) {

    var modal = require('ui.modal');
    var client = require('client');
    var Widget = require('ui.widget.Widget');
    var object = require('util.object');
    var event = require('event');
    var action = require('action');
    var loader = require('ui.loader');

    var Form = function (node, options) {
        Widget.call(this, node, options);
    };

    object.inherits(Form, Widget);

    Form.prototype.init = function() {
        this.initTimeInput();
        this.initScheduling();
    };

    Form.prototype.initTimeInput = function(evt) {
        var $timeFields = modal.global.$.find('.timeField');
        var $timeInputs =  $timeFields.find('.form-control');
        $timeInputs.each(function() {
            var $this = $(this);
            if($this.prop('disabled')) {
                $this.data('oldVal', $this.val()).val('');
            }
        });
    };

    Form.prototype.initScheduling = function(evt) {
        var $schedulingTab = modal.global.$.find('.tab-scheduling');
        var $checkBox = modal.global.$.find('#task-scheduling');
        var $calMode = modal.global.$.find('.field-task-cal_mode');
        if($checkBox.prop('checked')) {
            $schedulingTab.show();
            $calMode.show();
        } else {
            $schedulingTab.hide();
            $calMode.hide();
        }

        var $startInput  = $('#taskform-start_date');
        var $endInput= $('#taskform-end_date');

        $endInput.on('change', function() {
            if(!$startInput.val()) {
                $startInput.val($endInput.val());
            }
        });

        $startInput.on('change', function() {
            if(!$endInput.val()) {
                $endInput.val($startInput.val());
            }
        });
    };

    Form.prototype.toggleScheduling = function(evt) {
        var $schedulingTab = modal.global.$.find('.tab-scheduling');
        var $calMode = modal.global.$.find('.field-task-cal_mode');
        if (evt.$trigger.prop('checked')) {
            $schedulingTab.show();
            $calMode.show();
        } else {
            $schedulingTab.hide();
            $calMode.hide();
        }
    };

    Form.prototype.toggleDateTime = function(evt) {
        var $timeFields = modal.global.$.find('.timeField');
        var $timeInputs =  $timeFields.find('.form-control');
        if (evt.$trigger.prop('checked')) {
            $timeInputs.prop('disabled', true);
            $timeInputs.each(function() {
                $(this).data('oldVal', $(this).val()).val('');
            });
            $timeFields.css('opacity', '0.2');
        } else {
            $timeInputs.each(function() {
                $this = $(this);
                if($this.data('oldVal')) {
                    $this.val($this.data('oldVal'));
                }
            });
            $timeInputs.prop('disabled', false);
            $timeFields.css('opacity', '1.0');
        }
    };

    Form.prototype.removeTaskItem = function (evt) {
        evt.$trigger.closest('.form-group').remove();
    };

    Form.prototype.addTaskItem = function (evt) {
        var $this = evt.$trigger;
        $this.prev('input').tooltip({
            html: true,
            container: 'body'
        });

        var $newInputGroup = $this.closest('.form-group').clone(false);
        var $input = $newInputGroup.find('input');

        $input.val('');
        $newInputGroup.hide();
        $this.closest('.form-group').after($newInputGroup);
        $this.children('span').removeClass('glyphicon-plus').addClass('glyphicon-trash');
        $this.off('click.humhub-action').on('click', function () {
            $this.closest('.form-group').remove();
        });
        $this.removeAttr('data-action-click');
        $newInputGroup.fadeIn('fast');
    };


    var deleteTask = function(evt) {
         var widget = Widget.closest(evt.$trigger);

        widget.$.fadeOut('fast');

        client.post(evt).then(function() {
            // in case the modal delete was clicked
            modal.global.close();
            if(widget) {
                widget.$.remove()
            }

            event.trigger('task.afterDelete')
        }).catch(function(e) {
            widget.$.fadeIn('fast');
            module.log.error(e, true);
        });
     };

    /**
     * @param evt
     */
    var extensionrequest = function(evt) {
        evt.block = action.BLOCK_MANUAL;
        client.post(evt).then(function(response) {
            if(response.success) {
                var dropdownLink = Widget.closest(evt.$trigger);
                dropdownLink.reload().then(function() {
                    dropdownLink.hide();
                    module.log.success('request sent');
                });
            } else {
                module.log.error(e, true);
                evt.finish();
            }
        }).catch(function(e) {
            module.log.error(e, true);
            evt.finish();
        });
    };

    var changeState = function(evt) {
        evt.block = action.BLOCK_MANUAL;
        var widget = Widget.closest(evt.$target);
        if(!widget || !widget.changeState) {
            client.post(evt).then(function(response) {
                if(response.success) {
                    client.reload();
                } else {
                    module.log.error(e, true);
                }
            }).catch(function(e) {
                module.log.error(e, true);
                evt.finish();
            });
        } else {
            widget.changeState(evt);
        }
    };

    var init = function() {
        $(document).on('click', '.task-change-state-button a', function() {
           loader.initLoaderButton($('.task-change-state-button').children().first()[0]);
        });
    };

    module.export({
        init: init,
        Form: Form,
        deleteTask: deleteTask,
        changeState: changeState,
        extensionrequest:extensionrequest
    });
})
;
