<template>
  <div class="web-wrapper">
	<div class="container-fluid mt-3">
	<div class="invites-create-component">
    <h2>Create Invite</h2>
    <form @submit.prevent="submitForm">
      <div class="form-group">
        <label for="email">Email</label>
        <input
          type="email"
          id="email"
          v-model="form.email"
          class="form-control"
          required
        >
        <div v-if="errors.email" class="text-danger">{{ errors.email[0] }}</div>
      </div>
      <div class="form-group">
        <label for="message">Message</label>
        <textarea
          id="message"
          v-model="form.message"
          class="form-control"
        ></textarea>
        <div v-if="errors.message" class="text-danger">{{ errors.message[0] }}</div>
      </div>
      <button type="submit" class="btn btn-primary">Send Invite</button>
    </form>
    <div v-if="errors.general" class="alert alert-danger mt-3">
      {{ errors.general[0] }}
    </div>
    </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      form: {
        email: '',
        message: ''
      },
      errors: {}
    };
  },
  methods: {
    submitForm() {
      axios
        .post('/api/invites/create', this.form)
        .then(() => {
          this.$router.push('/i/invites');
        })
        .catch(error => {
          if (error.response && error.response.status === 422) {
            this.errors = error.response.data.errors;
          } else {
            console.error(error);
            this.errors = { general: ['Failed to create invite'] };
          }
        });
    }
  }
};
</script>

<style lang="scss" scoped>
	.invites-create-component {
		.bg-stellar {
			background: #7474BF;
			background: -webkit-linear-gradient(to right, #348AC7, #7474BF);
			background: linear-gradient(to right, #348AC7, #7474BF);
		}
		.font-default {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			letter-spacing: -0.7px;
		}

		.active {
			font-weight: 700;
		}
	}
</style>
