<template>
  <div class="grey lighten-5">
    
    <div class="teal darken-2 white-text z-depth-2">
      <div class="container center-align py-5">
        <h1 class="header light">Welcome to CoolPay</h1>
        <h5 class="light opacity-90">Digital payment made easy for daily transaction</h5>

        <div v-if="user" class="card-panel white-text teal lighten-1 max-w-md mx-auto mb-8">
          <h2 class="flow-text light">Hello, {{ user.name }}!</h2>
          <p class="mb-4 opacity-90">Welcome back to your tipping dashboard.</p>
          <a href="/dashboard" class="btn waves-effect waves-light white teal-text text-darken-2">
            Go to Dashboard
          </a>
        </div>

        <div v-else class="card-panel white-text teal lighten-1 max-w-md mx-auto mb-8">
          <h2 class="flow-text light">Get Started</h2>
          <p class="mb-4 opacity-90">Join thousands who are already earning tips digitally.</p>
          <div class="mt-4">
            <a href="/auth/login" class="btn waves-effect waves-light white teal-text text-darken-2 mr-3">
              Login
            </a>
            <a href="#" onclick="alert('Registration feature coming soon!')" class="btn waves-effect waves-light transparent white-text border-2">
              Sign Up
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="container section">
      <div class="row center-align">
        <h2 class="header">Why Choose Tippaz?</h2>
        <p class="grey-text text-darken-1 col s12 m8 offset-m2">Experience the future of tipping with our secure, fast, and user-friendly platform.</p>
      </div>

      <div class="row">
        <div class="col s12 m4">
          <div class="card hoverable">
            <div class="card-content center-align">
              <div class="circle blue lighten-4 blue-text text-darken-3 custom-icon-box">
                <i class="material-icons large">qr_code</i>
              </div>
              <h3 class="card-title text-darken-4">QR Code Tipping</h3>
              <p class="grey-text text-darken-1">Generate unique QR codes for instant tipping. Perfect for restaurants, delivery services, and any service provider.</p>
            </div>
          </div>
        </div>

        <div class="col s12 m4">
          <div class="card hoverable">
            <div class="card-content center-align">
              <div class="circle green lighten-4 green-text text-darken-3 custom-icon-box">
                <i class="material-icons large">security</i>
              </div>
              <h3 class="card-title text-darken-4">Secure Payments</h3>
              <p class="grey-text text-darken-1">Powered by Paystack for secure and reliable payment processing. Your money is always safe with us.</p>
            </div>
          </div>
        </div>

        <div class="col s12 m4">
          <div class="card hoverable">
            <div class="card-content center-align">
              <div class="circle purple lighten-4 purple-text text-darken-3 custom-icon-box">
                <i class="material-icons large">account_balance_wallet</i>
              </div>
              <h3 class="card-title text-darken-4">Instant Withdrawals</h3>
              <p class="grey-text text-darken-1">Withdraw your earnings instantly to your bank account. Track all transactions in your personal wallet.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grey lighten-4 section">
      <div class="container">
        <div class="row center-align">
          <h2 class="header">How It Works</h2>
          <p class="grey-text text-darken-1 col s12 m8 offset-m2">Get started in just three simple steps</p>
        </div>

        <div class="row">
          <div class="col s12 m4 center-align">
            <div class="circle blue darken-2 white-text large-circle-icon mx-auto">1</div>
            <h3 class="flow-text light">Sign Up</h3>
            <p class="grey-text text-darken-1">Create your free account and set up your profile in minutes.</p>
          </div>
          <div class="col s12 m4 center-align">
            <div class="circle blue darken-2 white-text large-circle-icon mx-auto">2</div>
            <h3 class="flow-text light">Share Your Link</h3>
            <p class="grey-text text-darken-1">Generate and share your personalized tipping QR code or link.</p>
          </div>
          <div class="col s12 m4 center-align">
            <div class="circle blue darken-2 white-text large-circle-icon mx-auto">3</div>
            <h3 class="flow-text light">Receive Tips</h3>
            <p class="grey-text text-darken-1">Start receiving tips instantly and withdraw to your bank account.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="teal darken-2 white-text section center-align">
      <div class="container">
        <h2 class="header light">Ready to Start Earning?</h2>
        <h5 class="light opacity-90 mb-4">Join thousands of service providers already using Tippaz</h5>
        <a href="/auth/login" class="btn-large waves-effect waves-light white teal-text text-darken-2">
          Get Started Today
        </a>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'HomeComponent',
  props: {
    user: {
      type: Object,
      default: null
    }
  }
}
</script>

<style scoped>
/* Custom styles to replicate specific Tailwind spacing/sizing */
.py-5 {
  padding-top: 5rem !important;
  padding-bottom: 5rem !important;
}

.section {
    padding-top: 4rem !important;
    padding-bottom: 4rem !important;
}

.custom-icon-box {
  width: 6rem; /* Equivalent to w-12/h-12 in Tailwind */
  height: 6rem;
  line-height: 6rem; /* Center the icon vertically */
  margin: 0 auto 1.5rem auto; /* Center and add bottom margin */
  font-size: 3rem; /* Make the icon size consistent */
}

.large-circle-icon {
    width: 4rem; /* Equivalent to w-16/h-16 in Tailwind */
    height: 4rem;
    line-height: 4rem;
    margin: 0 auto 1rem auto;
    font-size: 1.5rem;
    font-weight: bold;
}

.header {
    margin-top: 0;
    margin-bottom: 0.5rem;
}

.flow-text {
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

/* Ensure buttons and elements that were originally centered/spaced correctly */
.center-align .btn-large {
    margin-top: 1rem;
}
.mr-3 {
    margin-right: 1.5rem;
}
.border-2 {
    border: 2px solid white !important;
}
</style>