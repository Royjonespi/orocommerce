
define(function(require) {
    'use strict';

    var PossibleShippingMethodsComponent,
        $ = require('jquery'),
        _ = require('underscore'),
        mediator = require('oroui/js/mediator'),
        __ = require('orotranslation/js/translator'),
        BaseComponent = require('oroui/js/app/components/base/component'),
        LoadingMaskView = require('oroui/js/app/views/loading-mask-view');
    
    PossibleShippingMethodsComponent = BaseComponent.extend({
        selectors: {
            toggleBtn: '#possible_shipping_methods_btn',
            possibleShippingMethodForm: '[data-content="possible_shipping_methods_form"]',
            possibleShippingMethodType: '[name$="possibleShippingMethodType"]',
            calculateShipping: '[name$="[calculateShipping]"]',
            shippingMethod: '[name$="[shippingMethod]"]',
            shippingMethodType: '[name$="[shippingMethodType]"]',
            estimatedShippingCost: '[name$="[estimatedShippingCost]"]',
            overriddenShippingCost: '[name$="[overriddenShippingCost]"]'
        },

        /**
         * @constructor
         * @param {Object} options
         */
        initialize: function(options) {
            this.$el = options._sourceElement;
            this.loadingMaskView = new LoadingMaskView({container: this.$el});
            var self = this;
            this.getPossibleShippingMethodForm().hide();
            this.getToggleButton().on('click', function(){
                self.getCalculateShippingElement().val(true);
                mediator.trigger('entry-point:order:trigger');
            });
            this.getPossibleShippingMethodForm().on(
                'change',
                this.selectors.possibleShippingMethodType,
                _.bind(this.onShippingMethodTypeChange, this
                )
            );
            mediator.on('entry-point:order:load:before', this.showLoadingMask, this);
            mediator.on('entry-point:order:load', this.onOrderChange, this);
            mediator.on('entry-point:order:load:after', this.hideLoadingMask, this);
        },

        showLoadingMask: function() {
            if (this.getCalculateShippingElement().val() === 'true') {
                this.loadingMaskView.show();
            }
        },

        hideLoadingMask: function() {
            if (this.loadingMaskView.isShown()) {
                this.loadingMaskView.hide();
            }
        },

        onOrderChange: function(e) {
            this.$totals = e.totals;
            if (e.possibleShippingMethods != undefined ) {
                this.getCalculateShippingElement().val(false);
                this.getToggleButton().parent('div').hide();
                this.$data = e.possibleShippingMethods;
                this.updatePossibleShippingMethods(e.possibleShippingMethods);
                this.getPossibleShippingMethodForm().show();
            } else {
                this.getPossibleShippingMethodForm().hide();
                this.getToggleButton().parent('div').show();
            }
        },

        updatePossibleShippingMethods: function(methods) {
            var self = this;
            var selectedMethod = this.getShippingMethodElement().val();
            var selectedType = this.getShippingMethodTypeElement().val();
            var selectedFound = false;
            var len = $.map(methods, function(n, i) { return i; }).length;
            var str = '';
            if (len > 0 ) {
                var i = 0;
                $.each( methods, function( name, method ) {
                    if ($(method.types).length > 0 ) {
                    str = str + '<div class="method_title">';
                        if (method.isGrouped == true) {
                            str = str + '<span>' + __(method.label) + '</span>';
                        }
                    str = str + '</div>';
                    $.each( method.types, function( key, type ) {
                        if (type.price.value != null) {
                            str = str + '<div><label>';
                            var checked = '';
                            if (method.identifier === selectedMethod && type.identifier === selectedType) {
                                checked = 'checked="checked"';
                                selectedFound = true;
                                self.updateElementsValue(selectedType, selectedMethod, type.price.value);
                            }
                            str = str + '<input type="radio" ' + checked + ' name="possibleShippingMethodType" value="' + type.identifier + 
                            '" data-shipping-method="' + method.identifier + '" data-shipping-price="' + type.price.value + '" data-choice="' + type.identifier + '" />';
                            str = str + '<span>' + __(type.label) + ': <strong>' + type.price.currency + ' ' + type.price.value + '</strong></span>';
                            str = str + '</label></div>';
                        }
                    });   
                    }
                    i = i + 1;
                    if (len > i) {
                        str = str + '<hr>';
                    }
                });
                if (selectedFound === false) {
                    $(document).find('.selected-shipping-method').find('input').css('text-decoration', 'line-through');
                    this.setElementsValue(null, null, null);
                }
                this.getPossibleShippingMethodForm().html(str);
            } else {
                $(document).find('.selected-shipping-method').find('input').css('text-decoration', 'line-through');
                this.setElementsValue(null, null, null);
                str = '<span class="notification notification_xmd notification_alert notification-radiused mb1-md">' +
                    __('oro.order.possible_shipping_methods.no_method') +
                    '</span>';
                this.getPossibleShippingMethodForm().html(str);
            }
        },

        /**
         * @param {string|null} type
         * @param {string|null} method
         * @param {number} cost
         */
        setElementsValue: function (type, method, cost) {
            this.getShippingMethodTypeElement().val(type);
            this.getShippingMethodElement().val(method);
            this.getEstimatedShippingCostElement().val(cost);
        },

        /**
         * @param {string|null} type
         * @param {string|null} method
         * @param {number|null} cost
         */
        updateSelectedShippingMethod: function (type, method, cost) {
            if (type !== null && method != null) {
                var methodLabel = (this.$data[method].isGrouped == true) ? __(this.$data[method].label) + ', ' : '';
                var typeLabel = __(this.$data[method].types[type].label);
                var currency = this.$data[method].types[type].price.currency;
                var $div = $("<div>", {"class": "control-group selected-shipping-method"});
                $div.append('<label class="control-label">' + __('oro.order.shipping_method.label') + '</label>');
                $div.append('<div class="controls"><input type="text" readonly value="' + methodLabel +
                    typeLabel + ': ' + currency + ' ' + cost + '"></div>');
                $(document).find('.selected-shipping-method').remove();
                this.$el.closest('.responsive-cell').prepend($div);
            }
        },

        /**
         * @param {number} cost
         */
        updateTotals: function(cost) {
            if (cost !== null) {
                var totals = _.clone(this.$totals);
                var newTotalAmount = 0;
                $.each( totals.subtotals, function( key, subtotal ) {
                    if (subtotal.type === 'shipping_cost') {
                        totals.subtotals[key].amount = cost;
                        totals.subtotals[key].formattedAmount = subtotal.currency + ' ' + cost;
                    }
                    newTotalAmount = newTotalAmount + parseInt(totals.subtotals[key].amount, 10);
                });
                totals.total.amount = newTotalAmount;
                totals.total.formattedAmount = totals.total.currency + ' ' +  newTotalAmount;

                mediator.trigger('shipping-cost:updated', {'totals': totals});
            }
        },

        /**
         * @param {string|null} type
         * @param {string|null} method
         * @param {number|null} estimated_cost
         */
        updateElementsValue: function (type, method, estimated_cost) {
            var overridden_cost = this.getOverriddenShippingCostElement().val();
            var cost = (isNaN(parseFloat(overridden_cost))) ? estimated_cost : parseFloat(overridden_cost);
            this.setElementsValue(type, method, estimated_cost);
            this.updateSelectedShippingMethod(type, method, estimated_cost);
            this.updateTotals(cost);
        },

        /**
         * @param {Event} event
         */
        onShippingMethodTypeChange: function(event) {
            var method_type = $(event.target);
            var method = method_type.data('shipping-method');
            var estimated_cost = method_type.data('shipping-price');
            this.updateElementsValue(method_type.val(), method, estimated_cost);
        },

        /**
         * @returns {jQuery|HTMLElement}
         */
        getToggleButton: function() {
            if (!this.hasOwnProperty('$toggleButton')) {
                this.$toggleButton = this.$el.find(this.selectors.toggleBtn);
            }

            return this.$toggleButton;
        },

        /**
         * @returns {jQuery|HTMLElement}
         */
        getPossibleShippingMethodForm: function() {
            if (!this.hasOwnProperty('$possibleShippingMethodForm')) {
                this.$possibleShippingMethodForm = this.$el.find(this.selectors.possibleShippingMethodForm);
            }

            return this.$possibleShippingMethodForm;
        },

        /**
         * @returns {jQuery|HTMLElement}
         */
        getPossibleShippingMethodType: function() {
            if (!this.hasOwnProperty('$possibleShippingMethodType')) {
                this.$possibleShippingMethodType = this.$el.find(this.selectors.possibleShippingMethodType);
            }

            return this.$possibleShippingMethodType;
        },

        /**
         * @returns {jQuery|HTMLElement}
         */
        getShippingMethodTypeElement: function() {
            if (!this.hasOwnProperty('$shippingMethodTypeElement')) {
                this.$shippingMethodTypeElement = this.$el.find(this.selectors.shippingMethodType);
            }

            return this.$shippingMethodTypeElement;
        },

        /**
         * @returns {jQuery|HTMLElement}
         */
        getShippingMethodElement: function() {
            if (!this.hasOwnProperty('$shippingMethodElement')) {
                this.$shippingMethodElement = this.$el.find(this.selectors.shippingMethod);
            }

            return this.$shippingMethodElement;
        },

        /**
         * @returns {jQuery|HTMLElement}
         */
        getEstimatedShippingCostElement: function() {
            if (!this.hasOwnProperty('$estimatedShippingCostElement')) {
                this.$estimatedShippingCostElement = this.$el.find(this.selectors.estimatedShippingCost);
            }

            return this.$estimatedShippingCostElement;
        },

        /**
         * @returns {jQuery|HTMLElement}
         */
        getOverriddenShippingCostElement: function() {
            if (!this.hasOwnProperty('$overriddenShippingCostElement')) {
                this.$overriddenShippingCostElement = $(document).find(this.selectors.overriddenShippingCost);
            }

            return this.$overriddenShippingCostElement;
        },

        /**
         * @returns {jQuery|HTMLElement}
         */
        getCalculateShippingElement: function() {
            if (!this.hasOwnProperty('$calculateShippingElement')) {
                this.$calculateShippingElement = this.$el.find(this.selectors.calculateShipping);
            }

            return this.$calculateShippingElement;
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }
            this.getToggleButton().off('click');
            this.getPossibleShippingMethodType().off('change');

            mediator.off('entry-point:order:load:before', this.showLoadingMask, this);
            mediator.off('entry-point:order:load', this.onOrderChange, this);
            mediator.off('entry-point:order:load:after', this.hideLoadingMask, this);

            PossibleShippingMethodsComponent.__super__.dispose.call(this);
        }
    });

    return PossibleShippingMethodsComponent;
});
