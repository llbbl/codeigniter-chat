-- Migration: Add Database Indexes for Chat Messages Table
-- Purpose: Improve query performance for common chat operations
-- Date: $(date)

-- Add composite index on (time, user) for efficient user filtering with time-based ordering
-- This index optimizes queries that filter by user and order by time
CREATE INDEX idx_messages_time_user ON messages (time DESC, user);

-- Add index on time column alone for pagination optimization
-- This index optimizes general message retrieval ordered by time
CREATE INDEX idx_messages_time ON messages (time DESC);

-- Add index on user column for user-specific message queries
-- This index optimizes queries that filter messages by specific users
CREATE INDEX idx_messages_user ON messages (user);

-- Add composite index on (user, time) for user message history
-- This index optimizes user-specific queries ordered by time
CREATE INDEX idx_messages_user_time ON messages (user, time DESC);

-- Add index on time column for range queries (ascending order)
-- This index optimizes time range queries
CREATE INDEX idx_messages_time_asc ON messages (time ASC);

-- Performance analysis comments:
-- 1. idx_messages_time_user: Optimizes getMsgByUserPaginated() method
-- 2. idx_messages_time: Optimizes getMsgPaginated() method (general message retrieval)
-- 3. idx_messages_user: Optimizes user filtering in getMsgByUser() method
-- 4. idx_messages_user_time: Provides alternative for user-specific time ordering
-- 5. idx_messages_time_asc: Optimizes ascending time range queries in getMsgByTimeRange()

-- Notes:
-- - DESC indexes are used because the application primarily orders by time DESC
-- - Composite indexes follow the rule: equality conditions first, then range/order conditions
-- - These indexes will significantly improve performance for large message datasets
-- - Monitor index usage with EXPLAIN statements and adjust as needed