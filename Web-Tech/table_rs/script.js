document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('reservationForm');
  const today = new Date();
  const dateInput = document.getElementById('date');
  const formStatus = document.getElementById('formStatus');
  const submitBtn = document.getElementById('submitBtn');

  // Set min and max dates
  const formattedDate = today.toISOString().split('T')[0];
  dateInput.setAttribute('min', formattedDate);
  const maxDate = new Date();
  maxDate.setDate(today.getDate() + 30);
  const formattedMaxDate = maxDate.toISOString().split('T')[0];
  dateInput.setAttribute('max', formattedMaxDate);

  // Handle form submission
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (validateForm()) {
      submitForm();
    }
  });

  // Real-time field validation
  const inputs = form.querySelectorAll('input, select, textarea');
  inputs.forEach(input => {
    input.addEventListener('blur', () => validateField(input));
  });

  function validateForm() {
    let isValid = true;

    const name = document.getElementById('name');
    const email = document.getElementById('email');
    const phone = document.getElementById('phone');
    const date = document.getElementById('date');
    const time = document.getElementById('time');
    const guests = document.getElementById('guests');
    const seating = document.getElementById('seating');

    if (!/^[A-Za-z\s]{2,50}$/.test(name.value.trim())) {
      showError(name, 'Please enter a valid name (2-50 characters, letters only)');
      isValid = false;
    } else clearError(name);

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      showError(email, 'Please enter a valid email address');
      isValid = false;
    } else clearError(email);

    if (!/^[\d\s\-()]{10,15}$/.test(phone.value.trim())) {
      showError(phone, 'Please enter a valid phone number');
      isValid = false;
    } else clearError(phone);

    const selectedDate = new Date(date.value);
    if (!date.value) {
      showError(date, 'Please select a date');
      isValid = false;
    } else if (selectedDate < today) {
      showError(date, 'Please select a current or future date');
      isValid = false;
    } else if (selectedDate > maxDate) {
      showError(date, 'Reservations can only be made up to 30 days in advance');
      isValid = false;
    } else if (selectedDate.getDay() === 1) {
      showError(date, 'We are closed on Mondays. Please select another day');
      isValid = false;
    } else clearError(date);

    if (!time.value) {
      showError(time, 'Please select a time');
      isValid = false;
    } else clearError(time);

    if (!guests.value) {
      showError(guests, 'Please select number of guests');
      isValid = false;
    } else clearError(guests);

    if (seating.value === 'private' && parseInt(guests.value) < 6) {
      showError(seating, 'Private rooms are only available for 6 or more guests');
      isValid = false;
    } else clearError(seating);

    return isValid;
  }

  function validateField(field) {
    switch (field.id) {
      case 'name':
        if (!/^[A-Za-z\s]{2,50}$/.test(field.value.trim())) {
          showError(field, 'Please enter a valid name (2-50 characters, letters only)');
        } else clearError(field);
        break;
      case 'email':
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim())) {
          showError(field, 'Please enter a valid email address');
        } else clearError(field);
        break;
      case 'phone':
        if (!/^[\d\s\-()]{10,15}$/.test(field.value.trim())) {
          showError(field, 'Please enter a valid phone number');
        } else clearError(field);
        break;
      case 'date':
        const selectedDate = new Date(field.value);
        const selectedDay = selectedDate.getDay();
        if (selectedDay === 1) {
          showError(field, 'We are closed on Mondays. Please select another day');
        } else if (selectedDate < today) {
          showError(field, 'Please select a current or future date');
        } else if (selectedDate > maxDate) {
          showError(field, 'Reservations can only be made up to 30 days in advance');
        } else clearError(field);
        break;
    }
  }

  function showError(field, message) {
    const errorElement = document.getElementById(field.id + 'Error');
    errorElement.innerText = message;
    field.classList.add('error');
  }

  function clearError(field) {
    const errorElement = document.getElementById(field.id + 'Error');
    errorElement.innerText = '';
    field.classList.remove('error');
  }

  function submitForm() {
    const formData = new FormData(form);
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    formStatus.innerHTML = '';

    fetch('./reserve.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.text())
      .then(text => {
        try {
          const data = JSON.parse(text);
          handleResponse(data);
        } catch {
          throw new Error('Invalid JSON: ' + text);
        }
      })
      .catch(error => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Confirm Reservation';
        formStatus.classList.add('error');
        formStatus.classList.remove('success');
        formStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Something went wrong. Please try again.';
      });
  }

  function handleResponse(data) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Confirm Reservation';

    if (data.success) {
      formStatus.classList.add('success');
      formStatus.classList.remove('error');
      formStatus.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;

      formStatus.innerHTML += `
        <div class="confirmation-details">
          <p><strong>Date:</strong> ${formatDate(document.getElementById('date').value)}</p>
          <p><strong>Time:</strong> ${formatTime(document.getElementById('time').value)}</p>
          <p><strong>Guests:</strong> ${document.getElementById('guests').value}</p>
          <p><strong>Seating:</strong> ${document.getElementById('seating').value}</p>
        </div>
      `;

      form.reset();
    } else {
      formStatus.classList.add('error');
      formStatus.classList.remove('success');
      formStatus.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
    }
  }

  function formatDate(dateStr) {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateStr).toLocaleDateString('en-US', options);
  }

  function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const suffix = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : hour;
    return `${displayHour}:${minutes} ${suffix}`;
  }
});
