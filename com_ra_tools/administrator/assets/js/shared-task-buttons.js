document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('form[data-task-target]');

    forms.forEach(function (form) {
        var taskFieldId = form.getAttribute('data-task-target');

        if (!taskFieldId) {
            return;
        }

        var taskInput = document.getElementById(taskFieldId);

        if (!taskInput) {
            return;
        }

        form.addEventListener('click', function (event) {
            var button = event.target.closest('[data-task]');

            if (!button || !form.contains(button)) {
                return;
            }

            var task = button.getAttribute('data-task');

            if (task) {
                taskInput.value = task;
            }
        });
    });
});
