<template>
  <div>
    <h2>My Invites</h2>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>Email</th>
          <th>Message</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="invite in invites" :key="invite.id">
          <td>{{ invite.id }}</td>
          <td>{{ invite.email }}</td>
          <td>{{ invite.message }}</td>
          <td>
            <button class="btn btn-danger btn-sm" @click="deleteInvite(invite.id)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
    <router-link to="/create" class="btn btn-primary">Invite Someone</router-link>
    <div v-if="errors.length" class="alert alert-danger mt-3">
      <ul>
        <li v-for="error in errors" :key="error">{{ error }}</li>
      </ul>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      invites: [],
      errors: []
    };
  },
  mounted() {
    this.fetchInvites();
  },
  methods: {
    fetchInvites() {
      axios
        .get('/settings/invites', { headers: { Accept: 'application/json' } })
        .then(response => {
          this.invites = response.data;
        })
        .catch(error => {
          console.error(error);
          this.errors = ['Failed to fetch invites'];
        });
    },
    deleteInvite(id) {
      if (confirm('Are you sure you want to delete this invite?')) {
        axios
          .post('/settings/invites/delete', { id })
          .then(() => {
            this.fetchInvites();
          })
          .catch(error => {
            console.error(error);
            this.errors = ['Failed to delete invite'];
          });
      }
    }
  }
};
</script>

