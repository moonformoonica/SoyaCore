document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.custom-select').forEach(function (select) {
        const trigger = select.querySelector('.custom-select-trigger');
        const label   = select.querySelector('.selected-label');
        const options = select.querySelectorAll('.custom-options li');
        const hidden  = select.querySelector('input[type="hidden"]');

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.custom-select.open').forEach(function (open) {
                if (open !== select) open.classList.remove('open');
            });
            select.classList.toggle('open');
        });

        options.forEach(function (option) {
            option.addEventListener('click', function () {
                options.forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');

                label.textContent = option.textContent;
                hidden.value = option.dataset.value;

                select.classList.remove('open');
            });
        });
    });

    document.addEventListener('click', function () {
        document.querySelectorAll('.custom-select.open').forEach(function (open) {
            open.classList.remove('open');
        });
    });

});