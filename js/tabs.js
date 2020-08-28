(function ($, Drupal) {

    Drupal.behaviors.donationTabs = {

        attach: function (context) {

            $(".donation-form", context).once().on("click", ".tab-pill", function(e) {

                e.preventDefault();

                let requestedFormSelector = $(this).attr("href");
                let otherForm = $(requestedFormSelector);
                let thisForm = otherForm.siblings(".tab-pane");

               // Mark this tab pill as active
               $(this)
                   .addClass("active")
                   .attr("aria-selected", "true");

               // Mark its counterpart in the other form as active
               otherForm.find(".tab-pill[href='" + requestedFormSelector + "']")
                   .addClass("active")
                   .attr("aria-selected", "true");

               // Mark this tab pill's sibling as inactive
               thisForm.find(".tab-pill").not(this)
                   .removeClass("active")
                   .attr("aria-selected", "false");

               // Mark the sibling's counterpart in the other form is inactive
               // console.log("[href='" + requestedFormSelector + "']");
               otherForm.find(".tab-pill").not("[href='" + requestedFormSelector + "']")
                   .removeClass("active")
                   .attr("aria-selected", "false");

               thisForm.removeClass("active");
               otherForm.addClass("active")

            });

        }

    };

})(jQuery, Drupal);
