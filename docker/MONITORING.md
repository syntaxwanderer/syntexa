# Monitoring Guide for Syntexa Framework

## What Monitoring Gives You

### 1. **System Visibility**

**See what's happening in real-time:**
- ✅ Are all 3 applications running? (Blockchain Server, Shop 1, Shop 2)
- ✅ Response times for each application
- ✅ CPU and memory usage per service
- ✅ Database connection pool status
- ✅ Request rates and error rates

**Example scenarios:**
- "Why is Shop 1 slow?" → Check Grafana dashboard → See high database query time
- "Is blockchain syncing?" → Check blockchain metrics → See transaction processing rate
- "Is RabbitMQ working?" → Check queue depth → See if messages are stuck

### 2. **Blockchain Monitoring**

**Track blockchain health:**
- ✅ Transaction processing rate (per node)
- ✅ Block creation rate
- ✅ Node synchronization status
- ✅ Mempool size (pending transactions)
- ✅ Blockchain database growth
- ✅ Consensus voting status (if using BFT)

**Example scenarios:**
- "Are all nodes in sync?" → Check blockchain height across nodes
- "Why is transaction slow?" → Check mempool size and processing rate
- "Is blockchain growing too fast?" → Check database size trends

### 3. **Problem Detection**

**Early warning system:**
- ✅ Application crashes → Immediate alerts
- ✅ Database overload → See connection pool exhaustion
- ✅ RabbitMQ queue buildup → See message backlog
- ✅ Memory leaks → See gradual memory increase
- ✅ Network issues → See connection failures

**Example scenarios:**
- Application stops responding → Grafana shows 0 requests → Alert triggers
- Database slow → Grafana shows high query time → Investigate queries
- RabbitMQ full → Grafana shows queue depth → Scale consumers

### 4. **Performance Optimization**

**Data-driven decisions:**
- ✅ Identify bottlenecks (which app is slowest?)
- ✅ Optimize based on real usage patterns
- ✅ Plan scaling (when to add more workers?)
- ✅ Capacity planning (when will we need more resources?)

**Example scenarios:**
- "Which shop has more traffic?" → Compare request rates
- "When do we need more database connections?" → See connection pool usage
- "Should we add more blockchain nodes?" → See transaction processing capacity

## Practical Use Cases

### Use Case 1: Daily Operations

**Morning check:**
1. Open Grafana → See all 3 apps are green ✅
2. Check blockchain sync status → All nodes in sync ✅
3. Check error rates → No errors ✅
4. Check response times → All under 100ms ✅

**Result:** System is healthy, can proceed with work.

### Use Case 2: Problem Investigation

**Problem:** "Shop 1 is slow"

**Investigation:**
1. Open Grafana → See Shop 1 response time spike
2. Check database metrics → High query time
3. Check connection pool → Pool exhausted
4. Check blockchain metrics → High transaction rate

**Result:** Database connection pool too small → Increase pool size

### Use Case 3: Capacity Planning

**Question:** "Can we handle 10x more traffic?"

**Analysis:**
1. Check current CPU/memory usage → 30% CPU, 50% memory
2. Check database capacity → 40% connections used
3. Check blockchain processing → 1000 tx/sec capacity, using 200 tx/sec

**Result:** Can handle 3-4x more, but need to plan for 10x (add more nodes)

### Use Case 4: Blockchain Health

**Question:** "Is blockchain working correctly?"

**Check:**
1. All 3 nodes have same blockchain height? ✅
2. Transactions are being processed? ✅
3. RabbitMQ queues are not backing up? ✅
4. No fork events? ✅

**Result:** Blockchain is healthy and synchronized

## What You Can Monitor

### Application Metrics
- Request rate (requests/second)
- Response time (p50, p95, p99)
- Error rate (4xx, 5xx errors)
- Active connections
- Memory usage
- CPU usage

### Database Metrics
- Connection pool usage
- Query execution time
- Database size
- Transaction rate
- Lock wait time

### Blockchain Metrics
- Transaction processing rate
- Block creation rate
- Mempool size
- Node synchronization status
- Blockchain database size
- Consensus voting (if applicable)

### RabbitMQ Metrics
- Queue depth
- Message rate (publish/consume)
- Connection count
- Consumer utilization

## Setting Up Dashboards

### Quick Start

1. **Open Grafana:** http://localhost:3000
2. **Login:** admin/admin
3. **Create Dashboard:**
   - Click "+" → "Create Dashboard"
   - Add panel → Select Prometheus data source
   - Add queries for metrics you want to see

### Recommended Dashboards

1. **System Overview**
   - All 3 applications status
   - Response times
   - Error rates

2. **Blockchain Dashboard**
   - Transaction rates per node
   - Blockchain height per node
   - Mempool size
   - Sync status

3. **Database Dashboard**
   - Connection pool usage
   - Query performance
   - Database sizes

4. **RabbitMQ Dashboard**
   - Queue depths
   - Message rates
   - Consumer status

## Alerts (Future Enhancement)

You can set up alerts for:
- Application down
- High error rate (>1%)
- Slow response time (>1 second)
- Database connection pool exhausted
- Blockchain nodes out of sync
- RabbitMQ queue backing up

## Summary

**Monitoring gives you:**
- 👁️ **Visibility** - See what's happening
- 🚨 **Early warnings** - Detect problems before users notice
- 📊 **Data** - Make decisions based on facts, not guesses
- 🔧 **Optimization** - Find and fix bottlenecks
- 📈 **Planning** - Know when to scale

**Without monitoring:** You're blind - problems are discovered by users, not by you.

**With monitoring:** You're in control - you see problems coming and fix them proactively.

