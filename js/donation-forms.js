(function ($, Drupal) {

    Drupal.behaviors.donationForms = {

        attach: function (context) {

            const allowedInputs = [
              8, 9, 37, 38, 39, 40, 48, 49, 50, 51, 52, 53, 54, 55 ,56, 57, 86, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105
            ];
            $('.field-other-amount', context)
                .once("other-amount-processed")
                .on("keydown", function(e) {
                    // Also allow '.'
                    let inputs = allowedInputs.concat([190]);
                    if (inputs.indexOf(e.which) === -1) {
                        return false;
                    }
                    return true;
                })
                .on("keyup blur", function() {
                    let formattedValue = this.value;
                    if (this.value.indexOf('.') === 0) {
                        // Don't allow . at beginning of string
                        formattedValue = this.value.slice(1);
                    }
                    else if (this.value.indexOf('.') > 0) {
                        // Don't allow more than two digits after dot
                        let parts = this.value.split('.');
                        if (parts.length > 2) {
                            formattedValue = parts[0] + '.' + parts[1].slice(0, 2);
                        }
                        else if (parts.length > 1) {
                            if (parts[1].length > 1) {
                                formattedValue = parts[0] + '.' + parts[1].slice(0, 2);
                            }
                        }
                    }
                    this.value = formattedValue.replace(/[^\d.]/g, '');
                });

            $('.field-account-number', context)
                .once("account-number-processed")
                .on("keydown", function(e) {
                    if (allowedInputs.indexOf(e.which) === -1) {
                        return false;
                    }
                    return true;
                })
                .on("keyup blur", function(e) {
                    let value = this.value.replace(/[^\d]/g, '');
                    this.value = value.slice(0, 8);
                });

            $('.field-sort-code', context)
                .once("sort-code-processed")
                .on("keydown", function(e) {
                    // Also allow '-'
                    let inputs = allowedInputs.concat([189]);
                    if (inputs.indexOf(e.which) === -1) {
                        return false;
                    }
                    return true;
                })
                .on("keyup blur", function(e) {

                    let raw = this.value.replace(/[^\d]/g, '');
                    let parts = raw.match(/.{1,2}/g);
                    let deleting = e.which === 8;
                    let formattedValue = '';
                    if (raw.length > 6) {
                        formattedValue = parts.slice(0, 3).join('-');
                    }
                    else if (raw.length > 1) {
                        formattedValue = parts.join('-');
                    }
                    else {
                        formattedValue = raw;
                    }
                    if (raw.length > 1 && raw.length < 6 && raw.length % 2 === 0 && !deleting) {
                        this.value = formattedValue + '-';
                    }
                    else {
                        this.value = formattedValue;
                    }

                });

        }

    };

})(jQuery, Drupal);
