<template>
  <div id="wrapper">
    <div id="user-info">
      Welcome, <b>{{ username }}</b>! 
      <a href="/auth/logout" class="logout-btn">Logout</a>
    </div>
    <p id="messagewindow">
      <span v-if="loading">Loading...</span>
      <template v-else>
        <div v-for="(message, index) in messages" :key="index">
          <b>{{ message.user }}</b>: {{ message.msg }}<br />
        </div>
      </template>
    </p>
    <div id="load-more-container" v-if="!loading">
      <button 
        id="load-more-btn" 
        v-if="hasMoreMessages" 
        @click="loadMoreMessages"
        :disabled="loadingMore"
      >
        {{ loadingMore ? 'Loading...' : 'Load More Messages' }}
      </button>
    </div>
    <form @submit.prevent="sendMessage">
      <div id="txt" v-show="!sending">
        Message: 
        <input 
          type="text" 
          v-model="message" 
          :class="{ 'error-field': error }" 
          placeholder="Type your message here" 
        />
        <div class="error" v-if="error">{{ error }}</div>
      </div>

      <div id="contentLoading" class="contentLoading" v-show="sending">  
        <img src="/images/blueloading.gif" alt="Loading data, please wait...">  
      </div><br />

      <input type="submit" value="Send" :disabled="sending" /><br />
    </form>
  </div>
</template>

<script>
export default {
  data() {
    return {
      username: this.$username,
      messages: [],
      message: '',
      error: '',
      loading: true,
      sending: false,
      loadingMore: false,
      currentPage: 1,
      hasMoreMessages: false
    };
  },
  mounted() {
    this.loadMessages();
  },
  methods: {
    async loadMessages() {
      try {
        const response = await fetch(`${this.$chatRoutes.api}?page=${this.currentPage}&per_page=10`);
        const data = await response.json();
        
        this.messages = data.messages || [];
        this.hasMoreMessages = data.pagination?.hasNext || false;
        this.loading = false;
      } catch (error) {
        console.error('Error loading messages:', error);
        this.loading = false;
      }
    },
    
    async loadMoreMessages() {
      if (this.loadingMore) return;
      
      this.loadingMore = true;
      this.currentPage++;
      
      try {
        const response = await fetch(`${this.$chatRoutes.api}?page=${this.currentPage}&per_page=10`);
        const data = await response.json();
        
        if (data.messages && data.messages.length > 0) {
          this.messages = [...this.messages, ...data.messages];
        }
        
        this.hasMoreMessages = data.pagination?.hasNext || false;
        this.loadingMore = false;
      } catch (error) {
        console.error('Error loading more messages:', error);
        this.currentPage--; // Revert page increment on failure
        this.loadingMore = false;
      }
    },
    
    async sendMessage() {
      // Clear previous errors
      this.error = '';
      
      // Validate message
      if (!this.message.trim()) {
        this.error = 'Message is required';
        return;
      }
      
      if (this.message.length > 500) {
        this.error = 'Message cannot exceed 500 characters';
        return;
      }
      
      this.sending = true;
      
      try {
        const formData = new FormData();
        formData.append('message', this.message);
        formData.append('action', 'postmsg');
        formData.append(this.$csrfToken, document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        const response = await fetch(this.$chatRoutes.update, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        const data = await response.json();
        
        if (data && data.success === false) {
          this.error = data.errors?.message || 'Failed to send message';
        } else {
          // Add message to the beginning of the list
          this.messages.unshift({
            user: this.username,
            msg: this.message
          });
          
          // Clear message field
          this.message = '';
        }
      } catch (error) {
        console.error('Error sending message:', error);
        this.error = 'Failed to send message. Please try again.';
      } finally {
        this.sending = false;
      }
    }
  }
};
</script>