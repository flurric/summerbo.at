const app = new Vue({

}).$mount('#app')


var inputs = document.querySelectorAll('input')
var theForm = document.getElementById("GoodForm")
var question = document.querySelectorAll('.faqQuestion')

theForm.addEventListener('focus', function(event){
  theirParent = event.target.parentNode
  classes = theirParent.classList
  theirParent.classList = classes + " inputFocussed"
}, true)

theForm.addEventListener("blur", function( event ) {
  theirParent = event.target.parentNode
  theirParent.classList = "inputWrapper"
}, true);

const accordions = document.querySelectorAll('.js-badger-accordion')
Array.from(accordions).forEach((accordion) => {
    const ba = new BadgerAccordion(accordion);
});

const nav = document.getElementById('nav')
const navToggleElem = document.querySelector('.js-nav-toggle')

nav.classList.add('inactive')

nav.addEventListener('click', navToggle)
function navToggle(event) {
  if (nav.classList.contains('inactive')) {
    nav.classList.replace('inactive', 'active')
  }
  else {
    nav.classList.replace('active', 'inactive')
  }
}
