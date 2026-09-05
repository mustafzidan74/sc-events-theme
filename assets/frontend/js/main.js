/*------------------------------------------------------------------
* Project:        Eventen - Event Conference HTML Templates
* Author:         HtmlDesignTemplates
* URL:            https://themeforest.net/user/htmldesigntemplates
* Created:        05/10/2024
-------------------------------------------------------------------
*/

/*
 TABLE OF CONTENT
  
  1.Timer Countdown
  2.Numer count-up
  3.Spinning letter animation
  4.Slick slider
  5.Popup search
  6.Lightbox Gallery
  7.Back-to-top Button 

*/
(function($) {
  "use strict";

  /*//Timer countdown start//*/

  document.addEventListener('DOMContentLoaded', function() {
    const days = document.getElementById('days');
    const hours = document.getElementById('hours');
    const minutes = document.getElementById('minutes');
    const seconds = document.getElementById('seconds');

    // Check if all elements are found
    const elementsExist = days && hours && minutes && seconds;

    // If any element is missing, log a warning and return
    if (!elementsExist) {
        console.warn('One or more countdown elements not found.');
        return;
    }

    // Get current date and time
    const currentTime = new Date();

    // Set new date 30 days from now
    const targetTime = new Date(currentTime.getTime() + (30 * 24 * 60 * 60 * 1000));

    // Update countdown time
    function updateCountdown() {
        const currentTime = new Date();
        const diff = targetTime - currentTime;

        const d = Math.floor(diff / 1000 / 60 / 60 / 24);
        const h = Math.floor((diff / 1000 / 60 / 60) % 24);
        const m = Math.floor((diff / 1000 / 60) % 60);
        const s = Math.floor((diff / 1000) % 60);

        // Update elements if they exist
        if (days) days.innerHTML = d;
        if (hours) hours.innerHTML = h < 10 ? '0' + h : h;
        if (minutes) minutes.innerHTML = m < 10 ? '0' + m : m;
        if (seconds) seconds.innerHTML = s < 10 ? '0' + s : s;
    }

    // Call updateCountdown initially and set it to run every second
    updateCountdown();
    setInterval(updateCountdown, 1000);
});


  /*//Timer countdown end//*/


  /*//For Counterup Start//*/
  let valueDisplays = document.querySelectorAll(".num");
  let interval = 4000;

  valueDisplays.forEach((valueDisplay) => {
      let startValue = 0;
      let endValue = parseInt(valueDisplay.getAttribute("data-val"));
      let duration = Math.floor(interval / endValue);
      let counter = setInterval(function() {
          startValue += 1;
          valueDisplay.textContent = startValue;
          if (startValue == endValue) {
              clearInterval(counter);
          }
      }, duration);
  });
  /*//For Counterup end//*/


  /*//For Spinning letter animation start//*/
  const listItems = document.querySelectorAll('.spin');
  listItems.forEach((item, index) => {
      item.style.transitionDelay = `${index * 0.1}s`;
  });
  /*//For Spinning letter animation end //*/


  /*//For slick slider start//*/
  $('.testimonial-slide').slick({
      infinite: true,
      slidesToShow: 2,
      slidesToScroll: 2,
      autoplay: true,
      autoplaySpeed: 2000,
      arrows: false,
      dots: false,
      responsive: [{
          breakpoint: 600,
          settings: {
              slidesToShow: 1,
              slidesToScroll: 1
          }
      }]
  });
  /*//For slick slider end//*/

  /*//For Popup search start//*/
  $('a[href="#search1"]').on('click', function(event) {
      event.preventDefault();
      $('#search1').addClass('open');
      $('#search1 > form > input[type="search"]').focus();
  });
  $('#search1, #search1 button.close').on('click keyup', function(event) {
      if (event.target == this || event.target.className == 'close' || event.keyCode == 27) {
          $(this).removeClass('open');
      }
  });

  /*//For Popup search end//*/

  // Lightbox Gallery Start
  lightGallery(document.getElementById('selector'), {
      plugins: [lgThumbnail],
      speed: 500,
      licenseKey: 'your_license_key',
      animateThumb: true,
      zoomFromOrigin: false,
      allowMediaOverlap: true,
      toggleThumb: true,
  });


  lightGallery(document.getElementById('selector1'), {
      selector: '.item',
      plugins: [lgThumbnail],
      allowMediaOverlap: true,
      toggleThumb: true,
  });
  // Lightbox Gallery end


//   /*Masory js*/
//   window.onload = () => {
//       const grid = document.querySelector('.grid');

//       const masonry = new Masonry(grid, {
//           itemSelector: '.grid-item',
//           gutter: 1,
//           percentPosition: true
//       });
//   }
//   /*Masory js end*/

  /*Back-to-top Button start*/

  $(document).on('click', '#back-to-top, .back-to-top', () => {
      $('html, body').animate({
          scrollTop: 0
      }, '500');
      return false;
  });
  $(window).on('scroll', () => {
      if ($(window).scrollTop() > 500) {
          $('#back-to-top').fadeIn(200);
      } else {
          $('#back-to-top').fadeOut(200);
      }
  });
  /*Back-to-top Button end*/

  /*Slick Slider for product single page*/

  $('.slider-for').slick({
      slidesToShow: 1,
      slidesToScroll: 1,
      arrows: false,
      asNavFor: '.slider-nav'
  });
  $('.slider-nav').slick({
      slidesToShow: 4,
      slidesToScroll: 0,
      asNavFor: '.slider-for',
      dots: false,
      arrows: false,
      centerMode: true,
      focusOnSelect: true,
      responsive: [{
          breakpoint: 600,
          settings: {
              slidesToShow: 4,
              slidesToScroll: 1
          }
      }]
  });
})(jQuery);