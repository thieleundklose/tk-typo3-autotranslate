define([], function () {
    const initializeRecursiveLevelFilter = function () {
        document.querySelectorAll('[data-autotranslate-level-select]').forEach(function (element) {
            element.addEventListener('change', function (event) {
                const target = event.currentTarget;
                if (target && target.value) {
                    window.location.href = target.value;
                }
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeRecursiveLevelFilter);
    } else {
        initializeRecursiveLevelFilter();
    }

    return {};
});
