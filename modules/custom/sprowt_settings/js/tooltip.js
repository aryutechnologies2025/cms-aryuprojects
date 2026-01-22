(function(tippy) {


    window.SprowtToolTip = {
        instances: {},
        show: function(el, content, options = { array: true }) {
            let t = this;
            if(this.instances[el]) {
                this.instances[el].show();
            }
            else {
                options.content = content;
                options.onHidden = function(instance) {
                    instance.destroy();
                    if(t.instances[el]) {
                        delete t.instances[el];
                    }
                };
                let instance = tippy(el, options);
                instance.show();
                this.instances[el] = instance;
            }
        },
        hide: function(el) {
            if(this.instances[el]) {
                this.instances[el].hide();
            }
        }
    };

})(tippy);
