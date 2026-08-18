"""
Unit & Integration Tests for Waiting Room Queue Manager (app/queue_manager.py)
"""

import pytest
import asyncio
from app.queue_manager import QueueManager, calculate_queue_score, queue_websocket_endpoint
from app.schemas import QueuePriority, ClientRole


def test_calculate_queue_score_priority_ordering():
    ts = 1723896000000
    score_urgente = calculate_queue_score(QueuePriority.URGENTE, ts)
    score_preferencial = calculate_queue_score(QueuePriority.PREFERENCIAL, ts)
    score_normal = calculate_queue_score(QueuePriority.NORMAL, ts)

    assert score_urgente < score_preferencial < score_normal

    # FIFO within same priority
    score_normal_earlier = calculate_queue_score(QueuePriority.NORMAL, ts)
    score_normal_later = calculate_queue_score(QueuePriority.NORMAL, ts + 1000)
    assert score_normal_earlier < score_normal_later


@pytest.mark.asyncio
async def test_queue_entry_and_priority_ranking():
    qm = QueueManager()
    unit = "3205002"  # São Mateus

    # 1. Normal priority joins at t0
    t1 = await qm.join_queue(unit, user_id=1, name="Normal User", municipio="São Mateus", prioridade=QueuePriority.NORMAL)
    # 2. Another normal joins at t1
    t2 = await qm.join_queue(unit, user_id=2, name="Second Normal", municipio="São Mateus", prioridade=QueuePriority.NORMAL)
    # 3. Urgente priority joins at t2 (should jump ahead of normals)
    t3 = await qm.join_queue(unit, user_id=3, name="Urgent User", municipio="São Mateus", prioridade=QueuePriority.URGENTE)

    # Check status
    status_bc = await qm.get_queue_status(unit)
    assert status_bc.total_waiting == 3
    # First item must be the urgent user
    assert status_bc.items[0].ticket_id == t3.ticket_id
    assert status_bc.items[1].ticket_id == t1.ticket_id
    assert status_bc.items[2].ticket_id == t2.ticket_id

    # Check position rankings
    pos3, _ = await qm.get_queue_position(unit, t3.ticket_id)
    pos1, _ = await qm.get_queue_position(unit, t1.ticket_id)
    pos2, _ = await qm.get_queue_position(unit, t2.ticket_id)

    assert pos3 == 1
    assert pos1 == 2
    assert pos2 == 3


@pytest.mark.asyncio
async def test_atomic_ticket_claiming():
    qm = QueueManager()
    unit = "unit_vitoria"

    ticket = await qm.join_queue(unit, user_id=10, name="Citizen", municipio="Vitória")

    # Technician A claims ticket
    success_a, msg_a = await qm.claim_and_admit_attendee(
        unit_id=unit,
        ticket_id=ticket.ticket_id,
        technician_id=101,
        technician_name="Dra. Marcia",
        room_id="sala-101"
    )
    assert success_a is True
    assert msg_a == "SUCCESS"

    # Technician B attempts to claim the exact same ticket -> must fail atomically
    success_b, msg_b = await qm.claim_and_admit_attendee(
        unit_id=unit,
        ticket_id=ticket.ticket_id,
        technician_id=102,
        technician_name="Dr. Roberto",
        room_id="sala-102"
    )
    assert success_b is False
    assert msg_b in ["TICKET_NOT_IN_QUEUE", "TICKET_ALREADY_CLAIMED"]


@pytest.mark.asyncio
async def test_queue_departure_and_position_recalculation():
    qm = QueueManager()
    unit = "3201506"  # Colatina

    t1 = await qm.join_queue(unit, user_id=1, name="User 1", municipio="Colatina")
    t2 = await qm.join_queue(unit, user_id=2, name="User 2", municipio="Colatina")

    pos2_before, _ = await qm.get_queue_position(unit, t2.ticket_id)
    assert pos2_before == 2

    # User 1 leaves
    await qm.remove_ticket(unit, t1.ticket_id, reason="cancelled")

    # User 2 should now be at position 1
    pos2_after, total = await qm.get_queue_position(unit, t2.ticket_id)
    assert pos2_after == 1
    assert total == 1


@pytest.mark.asyncio
async def test_queue_websocket_admission_flow(token_factory, ws_session_factory):
    unit_id = "3205002"

    token_tech = token_factory(user_id=101, name="Dra. Marcia", role="tecnico", unit_id=unit_id)
    token_att = token_factory(user_id=502, name="Lucas", role="egresso", unit_id=unit_id)

    ws_att = ws_session_factory()
    ws_tech = ws_session_factory()

    task_att = asyncio.create_task(queue_websocket_endpoint(ws_att, unit_id=unit_id, token=token_att))
    try:
        # Attendee joins queue
        await ws_att.inbox.put({
            "type": "join_queue",
            "name": "Lucas",
            "municipio": "São Mateus",
            "prioridade": "urgente"
        })

        joined_ack = await asyncio.wait_for(ws_att.outbox.get(), timeout=2.0)
        assert joined_ack["type"] == "queue_joined"
        ticket_id = joined_ack["ticket_id"]
        assert joined_ack["position"] >= 1

        # Technician connects
        task_tech = asyncio.create_task(queue_websocket_endpoint(ws_tech, unit_id=unit_id, token=token_tech))
        try:
            queue_status = await asyncio.wait_for(ws_tech.outbox.get(), timeout=2.0)
            assert queue_status["type"] == "queue_status"
            assert queue_status["total_waiting"] >= 1

            # Technician admits attendee
            target_room = "sala-vitoria-sm-101"
            await ws_tech.inbox.put({
                "type": "admit_attendee",
                "ticket_id": ticket_id,
                "room_id": target_room
            })

            # Attendee receives call push notification
            call_msg = await asyncio.wait_for(ws_att.outbox.get(), timeout=2.0)
            assert call_msg["type"] == "call_attendee"
            assert call_msg["ticket_id"] == ticket_id
            assert call_msg["room_id"] == target_room
            assert call_msg["tecnico_name"] == "Dra. Marcia"

        finally:
            await ws_tech.close()
            try:
                await asyncio.wait_for(task_tech, timeout=1.0)
            except Exception:
                pass
    finally:
        await ws_att.close()
        try:
            await asyncio.wait_for(task_att, timeout=1.0)
        except Exception:
            pass
