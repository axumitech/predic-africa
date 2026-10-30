import os
import sys
import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path
from unittest.mock import patch

# Set the DB URL before imports; never open a persistent development database.
TEMP = tempfile.TemporaryDirectory(prefix="predic-python-tests-")
os.environ["AI_DATABASE_URL"] = "sqlite:///" + str(Path(TEMP.name) / "pipeline.sqlite")
os.environ["AI_PIPELINE_SCHEDULER_ENABLED"] = "false"
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from fastapi.testclient import TestClient
from database.database import Base, engine, SessionLocal
from database.models import GenericMarket, Match, Prediction
from models.predictor import generate_predictions
import main
from market_generator.publisher import publish_market


class PipelineApiTest(unittest.TestCase):
    def setUp(self):
        Base.metadata.drop_all(bind=engine)
        Base.metadata.create_all(bind=engine)
        self.env = patch.dict(os.environ, {"AI_PIPELINE_TOKEN": "test-only-token"})
        self.env.start()
        self.client = TestClient(main.app)

    def tearDown(self):
        self.client.close()
        self.env.stop()

    def count(self):
        with SessionLocal() as db:
            return db.query(GenericMarket).count()

    def test_remote_generation_requires_token_and_ignores_forwarded_ip(self):
        for headers in [{}, {"X-Pipeline-Token": "wrong"}, {"X-Forwarded-For": "127.0.0.1"}]:
            self.assertEqual(self.client.post("/api/markets/generate", headers=headers).status_code, 403)
        self.assertEqual(self.count(), 0)
        response = self.client.post("/api/markets/generate", headers={"X-Pipeline-Token": "test-only-token"})
        self.assertEqual(response.status_code, 200)
        self.assertTrue(response.json()["simulation"])
        self.assertEqual(self.count(), 2)
        for item in self.client.get("/api/markets").json()["data"]:
            self.assertTrue(item["title"].startswith("Démo"))
            self.assertEqual(item["options"], ["Oui", "Non"])
            self.assertIsNotNone(item["generated_at"])

    def test_unconfigured_token_allows_only_local_demo(self):
        with patch.dict(os.environ, {"AI_PIPELINE_TOKEN": ""}):
            self.assertEqual(self.client.post("/api/markets/generate").status_code, 403)
            with TestClient(main.app, client=("127.0.0.1", 50000)) as local:
                self.assertEqual(local.post("/api/markets/generate").status_code, 200)

    def test_second_suggestion_failure_rolls_back_entire_batch(self):
        calls = 0
        def broken_publish(db, data):
            nonlocal calls
            calls += 1
            if calls == 2:
                raise RuntimeError("private upstream detail")
            return publish_market(db, data)
        with patch.object(main, "publish_market", side_effect=broken_publish):
            response = self.client.post("/api/markets/generate", headers={"X-Pipeline-Token": "test-only-token"})
        self.assertEqual(response.status_code, 503)
        self.assertNotIn("private upstream detail", response.text)
        self.assertEqual(self.count(), 0)
        self.assertEqual(self.client.post("/api/markets/generate", headers={"X-Pipeline-Token": "test-only-token"}).status_code, 200)
        self.assertEqual(self.count(), 2)

    def test_catalog_omits_expired_and_inactive_rows_and_labels_simulation_utc(self):
        now = datetime.now(timezone.utc).replace(tzinfo=None)
        with SessionLocal() as db:
            for expiry, status in [(now + timedelta(days=1), "active"), (now - timedelta(days=1), "active"), (now + timedelta(days=1), "closed")]:
                db.add(GenericMarket(title="Question de test", description="Règles fictives", category="crypto", options_json='["Oui", "Non"]', odds_json='[2.35, 1.7]', expiry_date=expiry, status=status))
            db.commit()
        result = self.client.get("/api/markets").json()
        self.assertTrue(result["simulation"])
        self.assertEqual(result["source"], "random_demo")
        self.assertEqual(len(result["data"]), 1)
        self.assertTrue(result["data"][0]["expiry"].endswith(("Z", "+00:00")))
        self.assertTrue(result["data"][0]["simulation"])

    def test_predictions_are_explicitly_simulated_and_exclude_past_matches(self):
        now = datetime.now(timezone.utc).replace(tzinfo=None)
        with SessionLocal() as db:
            for date in [now + timedelta(days=1), now - timedelta(days=1)]:
                db.add(Match(domain="football", home_team="Equipe A fictive", away_team="Equipe B fictive", match_date=date,
                             status="upcoming", home_odds=2, away_odds=2, draw_odds=3))
            db.commit()
            generate_predictions(db)
            for prediction in db.query(Prediction).all():
                self.assertIn("aucune statistique", prediction.explanation)
        response = self.client.get("/api/predictions").json()
        self.assertTrue(response["simulation"])
        self.assertEqual(len(response["data"]), 1)
        self.assertEqual(response["data"][0]["prediction"]["method"], "random_demo")
        self.assertTrue(response["data"][0]["prediction"]["explanation"].startswith("Simulation aléatoire non évaluée"))

    def test_cors_has_no_wildcard_browser_access_by_default(self):
        response = self.client.options("/api/markets/generate", headers={
            "Origin": "https://untrusted.example", "Access-Control-Request-Method": "POST"})
        self.assertEqual(response.status_code, 400)
        self.assertNotIn("access-control-allow-origin", response.headers)

    def test_lifespan_shuts_down_scheduler(self):
        with patch.dict(os.environ, {"AI_PIPELINE_SCHEDULER_ENABLED": "true"}), patch.object(main, "start_scheduler") as start:
            with TestClient(main.app):
                start.assert_called_once()
            start.return_value.shutdown.assert_called_once_with(wait=False)


if __name__ == "__main__":
    try:
        unittest.main()
    finally:
        engine.dispose()
        TEMP.cleanup()
