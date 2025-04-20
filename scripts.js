// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log("JavaScript loaded");

    // Get the role select element and the registration number input field
    const roleSelect = document.getElementById('role');
    const regNoField = document.getElementById('reg_no');

    // Function to toggle the visibility of the registration number field
    function toggleRegNoField() {
        // Check if the selected role is 'student'
        if (roleSelect.value === 'student') {
            regNoField.style.display = 'block'; // Show the field
        } else {
            regNoField.style.display = 'none'; // Hide the field
        }
    }

    // Add event listener to the role select element
    roleSelect.addEventListener('change', toggleRegNoField);

    // Initial check to set the correct visibility on page load
    toggleRegNoField();
});