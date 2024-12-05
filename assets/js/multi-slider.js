      jQuery(document).ready(function($) {
          // Track if it's the first time setting the carousel
          let isFirstSet = true;

          // Initialize Materialize Carousel
          const initializeCarousel = () => {
              $('.carousel').carousel({
                  numVisible: 3,
                  padding: -600,
                  onCycleTo: function (event) {
                      let currentSlide = parseInt(event.name);
                      if (isFirstSet) {
                          // Set the correct slide based on hash
                          const hash = window.location.hash;
                          if (!hash) {
                              currentSlide = 1;
                          } else {
                              currentSlide = parseInt(hash.replace('#truth', ''));
                          }
                          $('.carousel').carousel('set', currentSlide - 1);
                          isFirstSet = false;
                      }

                      // Highlight the current navigation link
                      $('.carousel-nav .swiper-slide:nth-child(' + currentSlide + ') a').addClass('current');
                      $('.carousel-nav .swiper-slide:not(:nth-child(' + currentSlide + ')) a').removeClass('current');

                      // Sync Swiper with the current slide
                      swiper.slideTo(currentSlide - 1);

                      // Update the hash in the URL
                      window.location.hash = 'truth' + currentSlide;
                  },
              });
          };

          // Initialize Swiper
          const swiper = new Swiper('.mySwiper', {
              spaceBetween: 10,
              slidesOffsetBefore: 30,
              slidesOffsetAfter: 30,
              breakpoints: {
                  200: {
                      slidesPerView: 2,
                      slidesOffsetBefore: 25,
                      slidesOffsetAfter: 25,
                  },
                  360: {
                      slidesPerView: 3,
                      slidesOffsetBefore: 25,
                      slidesOffsetAfter: 25,
                  },
                  470: {
                      slidesPerView: 4,
                  },
                  700: {
                      slidesPerView: 5,
                  },
                  930: {
                      slidesPerView: 7,
                      slidesPerGroup: 7,
                  },
              },
              navigation: {
                  nextEl: '.swiper-button-next',
                  prevEl: '.swiper-button-prev',
              },
          });

          // Reinitialize carousel
          initializeCarousel();

          // Navigation click handler
          $('.carousel-nav .swiper-slide a').on('click', function (event) {
              event.preventDefault();
              const slideIndex = parseInt($(this).attr('name'));
              $('.carousel').carousel('set', slideIndex - 1);
          });

          // Handle arrow navigation
          $('#arrowPrev').on('click', function () {
              const currentSlide = parseInt(window.location.hash.replace('#truth', '')) || 1;
              if (currentSlide === 1) {
                  $('.carousel').carousel('set', $('.carousel .carousel-item').length - 1);
              } else {
                  $('.carousel').carousel('prev');
              }
          });

          $('#arrowNext').on('click', function () {
              const currentSlide = parseInt(window.location.hash.replace('#truth', '')) || 1;
              if (currentSlide === $('.carousel .carousel-item').length) {
                  $('.carousel').carousel('set', 0);
              } else {
                  $('.carousel').carousel('next');
              }
          });

          // Sync carousel and Swiper on page reload
          const syncOnReload = () => {
              const hash = window.location.hash;
              if (hash) {
                  const slideIndex = parseInt(hash.replace('#truth', '')) || 1;
                  $('.carousel').carousel('set', slideIndex - 1);
                  swiper.slideTo(slideIndex - 1);
              }
          };

          // Call sync function on page load
          syncOnReload();
      });
    